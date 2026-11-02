<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** The landscape cover photo on a seller's shop page: cropped around its centre and resized, like profile photos. */
class ShopCoverPhotos
{
    public const WIDTH = 1600;

    public const HEIGHT = 600;

    public static function rules(): array
    {
        return ['cover' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.ProfilePhotos::MAX_KB, 'dimensions:min_width=600,min_height=200']];
    }

    public static function messages(): array
    {
        return [
            'cover.required' => 'Choose a photo to upload.',
            'cover.mimes' => 'Use a JPG, PNG or WebP photo.',
            'cover.max' => 'The photo must be 5 MB or smaller.',
            'cover.dimensions' => 'The photo must be at least 600 × 200 pixels.',
        ];
    }

    public function replace(User $seller, UploadedFile $file): void
    {
        $previous = $seller->getRawOriginal('shop_cover');
        $seller->forceFill(['shop_cover' => $this->store($seller, $file)])->save();

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }
    }

    public function remove(User $seller): void
    {
        $previous = $seller->getRawOriginal('shop_cover');
        $seller->forceFill(['shop_cover' => null])->save();

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }
    }

    private function store(User $seller, UploadedFile $file): string
    {
        $size = @getimagesize($file->getRealPath());
        if ($size && $size[0] * $size[1] > ProfilePhotos::MAX_PIXELS) {
            throw ValidationException::withMessages(['cover' => 'This photo is too large. Use one under 40 megapixels.']);
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['cover' => 'This photo could not be read. Try a JPG or PNG.']);
        }
        $image = app(ProfilePhotos::class)->upright($image, $file);

        // The largest centred rectangle in the cover's shape.
        $width = imagesx($image);
        $height = imagesy($image);
        $cropWidth = min($width, (int) round($height * self::WIDTH / self::HEIGHT));
        $cropHeight = min($height, (int) round($cropWidth * self::HEIGHT / self::WIDTH));

        $cover = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($cover, 0, 0, imagecolorallocate($cover, 255, 255, 255));
        imagecopyresampled($cover, $image, 0, 0, intdiv($width - $cropWidth, 2), intdiv($height - $cropHeight, 2), self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight);

        [$data, $extension] = ProfilePhotos::encode($cover);
        $path = "shop-covers/{$seller->id}/".Str::random(40).'.'.$extension;
        // The disks do not throw, so a failed upload (say, to R2) must be caught here, not saved as a missing photo.
        if ($data === '' || ! Storage::disk('public')->put($path, $data)) {
            throw ValidationException::withMessages(['cover' => 'Your photo could not be saved right now. Please try again in a moment.']);
        }

        return $path;
    }
}
