<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class PsgcService
{
    public function regions(): array
    {
        return $this->get('regions');
    }

    public function provinces(string $region): array
    {
        return $this->get('regions/'.$this->code($region).'/provinces');
    }

    public function cities(string $province): array
    {
        return $this->get('provinces/'.$this->code($province).'/cities-municipalities');
    }

    public function provinceFreeCities(string $region): array
    {
        return array_values(array_filter(
            $this->get('regions/'.$this->code($region).'/cities-municipalities'),
            fn ($city) => empty($city['provinceCode'])
        ));
    }

    public function barangays(string $city): array
    {
        return $this->get('cities-municipalities/'.$this->code($city).'/barangays');
    }

    public function addressNames(array $data): array
    {
        $region = $this->find($this->regions(), $data['region_code'], 'region_code');
        $province = null;
        if (! empty($data['province_code'])) {
            $province = $this->find($this->provinces($region['code']), $data['province_code'], 'province_code');
            $cities = $this->cities($province['code']);
        } else {
            $cities = $this->provinceFreeCities($region['code']);
        }
        $city = $this->find($cities, $data['city_municipality_code'], 'city_municipality_code');
        $barangay = $this->find($this->barangays($city['code']), $data['barangay_code'], 'barangay_code');

        // Names come from PSGC, never from client-submitted display text.
        return [
            'region_name' => $region['name'],
            'province' => $province['name'] ?? '',
            'city' => $city['name'],
            'barangay' => $barangay['name'],
        ];
    }

    private function find(array $items, string $code, string $field): array
    {
        foreach ($items as $item) {
            if ($item['code'] === $code) {
                return $item;
            }
        }

        throw ValidationException::withMessages([$field => 'Choose a valid location belonging to the selected parent.']);
    }

    private function code(string $code): string
    {
        abort_unless(preg_match('/^[0-9]{9}$/D', $code), 404);

        return $code;
    }

    private function get(string $path): array
    {
        $base = rtrim(config('locations.base_url'), '/');

        return Cache::remember('psgc:v1:'.sha1($base.'/'.$path), config('locations.cache_seconds'), function () use ($base, $path) {
            try {
                $data = Http::acceptJson()->connectTimeout(3)
                    ->timeout(config('locations.timeout_seconds'))
                    ->get($base.'/'.$path.'/')->throw()->json();
                if (! is_array($data) || ! array_is_list($data)) {
                    throw new \UnexpectedValueException('Invalid PSGC response.');
                }
                foreach ($data as $item) {
                    if (! is_array($item) || ! is_string($item['code'] ?? null)
                        || ! preg_match('/^[0-9]{9}$/D', $item['code'])
                        || ! is_string($item['name'] ?? null) || $item['name'] === '') {
                        throw new \UnexpectedValueException('Invalid PSGC location.');
                    }
                }

                return collect($data)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
            } catch (\Throwable $exception) {
                // Failed responses are not cached. Existing saved addresses remain usable.
                throw new ServiceUnavailableHttpException(30, 'Location service is temporarily unavailable. Please try again.', $exception);
            }
        });
    }
}
