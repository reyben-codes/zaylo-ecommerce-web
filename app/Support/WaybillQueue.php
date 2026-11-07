<?php

namespace App\Support;

use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Waybills the logistics partner prints for sellers without a printer. Each such parcel gets the partner's next queue
 * number for the day and a colour that changes with every number, e.g. "07 · BLUE". The seller writes it big on the
 * parcel; the pickup list and the printed waybill show the same mark, so the label is matched to the right parcel.
 */
final class WaybillQueue
{
    /** Colour names riders and sellers can tell apart, with a print-safe swatch. */
    public const COLORS = [
        'red' => ['Red', '#d64545'],
        'blue' => ['Blue', '#2f6fde'],
        'green' => ['Green', '#2e8b57'],
        'yellow' => ['Yellow', '#e6b800'],
        'purple' => ['Purple', '#7b4fc9'],
        'orange' => ['Orange', '#e8772e'],
        'pink' => ['Pink', '#d94f9b'],
        'black' => ['Black', '#222222'],
    ];

    public static function ready(): bool
    {
        return Schema::hasColumn('shipments', 'partner_prints');
    }

    /** Gives the parcel the partner's next number for today (Philippine time), with its colour. Keeps an existing one. */
    public static function assign(Shipment $shipment): Shipment
    {
        if (! self::ready()) {
            return $shipment;
        }

        return DB::transaction(function () use ($shipment) {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($locked->queue_number) {
                return $locked;
            }
            $today = now('Asia/Manila')->toDateString();
            $provider = Shipment::tracksProvider() ? $locked->logistics_provider_id : null;
            $last = (int) Shipment::query()->whereDate('queue_date', $today)
                ->when(Shipment::tracksProvider(), fn ($q) => $provider ? $q->where('logistics_provider_id', $provider) : $q->whereNull('logistics_provider_id'))
                ->lockForUpdate()->max('queue_number');
            $number = $last + 1;
            $locked->forceFill([
                'partner_prints' => true,
                'queue_number' => $number,
                'queue_color' => array_keys(self::COLORS)[($number - 1) % count(self::COLORS)],
                'queue_date' => $today,
            ])->save();

            return $locked;
        });
    }

    /** @return array{number: string, color: string, name: string, hex: string, date: ?string}|null */
    public static function for(?Shipment $shipment): ?array
    {
        if (! $shipment || ! self::ready() || ! $shipment->partner_prints || ! $shipment->queue_number) {
            return null;
        }
        [$name, $hex] = self::COLORS[$shipment->queue_color] ?? ['Black', '#222222'];

        return [
            'number' => str_pad((string) $shipment->queue_number, 2, '0', STR_PAD_LEFT),
            'color' => (string) $shipment->queue_color,
            'name' => strtoupper($name),
            'hex' => $hex,
            'date' => $shipment->queue_date ? \Illuminate\Support\Carbon::parse($shipment->queue_date)->format('M j') : null,
        ];
    }
}
