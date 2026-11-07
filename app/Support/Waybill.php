<?php

namespace App\Support;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerApplication;
use App\Models\Shipment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * The printed label that travels with a parcel: tracking barcode and QR code, sender, recipient, contents and the
 * amount to collect. Riders scan either code in the app to start and to confirm a delivery. Works on the deployed
 * schema (orders carry the seller) and the newer one (seller orders).
 */
class Waybill
{
    /** 4 × 6 inch thermal label, in points. */
    private const PAPER = [0, 0, 288, 432];

    /** Cancelled parcels get no waybill; they should not move. The newer schema records cancelling on the seller order. */
    public static function available(?Shipment $shipment): bool
    {
        if ($shipment === null || $shipment->status === 'cancelled') {
            return false;
        }
        $sellerOrder = Schema::hasColumn('shipments', 'seller_order_id') ? $shipment->sellerOrder : null;

        return $sellerOrder ? $sellerOrder->status !== 'cancelled' : $shipment->order?->status !== 'cancelled';
    }

    public static function for(Shipment $shipment): array
    {
        $sellerOrder = Schema::hasColumn('shipments', 'seller_order_id') ? $shipment->sellerOrder : null;
        $order = $shipment->order ?: $sellerOrder?->order;
        $tracking = (string) ($shipment->tracking_number ?? $shipment->tracking_code ?? 'PARCEL-'.$shipment->id);

        return [
            'tracking' => $tracking,
            'barcode' => Code128::dataUri($tracking, 56, 2),
            'qr' => self::qrCode($tracking),
            'order' => $order?->order_number ?: '—',
            'placed' => $order?->placed_at ?? $order?->created_at,
            'sender' => $sellerOrder ? self::shopSender($sellerOrder->seller) : self::userSender($order?->seller),
            'recipient' => self::recipient($order),
            'items' => $items = self::items($order, $sellerOrder),
            'pieces' => $items->sum('quantity'),
            'weight' => self::weight($items),
            'cod' => $cod = self::amountToCollect($order, $sellerOrder, $shipment),
            'payment' => $order?->payment_method === 'cod' && $cod <= 0 ? 'Paid' : self::paymentLabel($order),
            'carrier' => $shipment->logistics_provider_id ? $shipment->provider?->name : null,
            'area' => Schema::hasColumn('shipments', 'sort_area') ? $shipment->sort_area : null,
            'notes' => trim((string) ($order?->notes ?? $order?->note ?? '')) ?: null,
            'printed' => now('Asia/Manila'),
            // The courier on the order picks the label layout (ZAYLO, J&T or Flash Express).
            'courier' => $courier = self::courierOf($order, $shipment),
            'courier_name' => (Order::COURIERS[$courier] ?? Order::COURIERS[Order::DEFAULT_COURIER])['name'],
            'sort_code' => self::sortCode($recipient = self::recipient($order)),
            // Printed by the logistics partner for a seller without a printer: the mark written on the parcel.
            'queue' => WaybillQueue::for($shipment),
        ];
    }

    /** Layouts for each courier (resources/views/waybill/layouts). */
    public const LAYOUTS = ['zaylo_express', 'jnt_express', 'flash_express'];

    /**
     * The layout follows the courier the parcel is assigned to: the logistics partner that picks it up when its name
     * is J&T or Flash Express, otherwise the courier the buyer chose at checkout (ZAYLO Express by default).
     */
    public static function courierOf(?Order $order, ?Shipment $shipment = null): string
    {
        $partner = $shipment && Shipment::tracksProvider() && $shipment->logistics_provider_id ? $shipment->provider?->name : null;
        if ($assigned = self::courierNamed($partner)) {
            return $assigned;
        }
        $courier = $order && Schema::hasColumn('orders', 'courier_choice') ? $order->courier_choice : null;

        return in_array($courier, self::LAYOUTS, true) ? $courier : Order::DEFAULT_COURIER;
    }

    /** "J&T Express Calamba" → jnt_express, "Flash Express Hub" → flash_express; other names have no layout of their own. */
    public static function courierNamed(?string $name): ?string
    {
        $name = strtolower((string) $name);

        return match (true) {
            $name === '' => null,
            (bool) preg_match('/\bj\s*&\s*t\b|\bjnt\b|\bj and t\b/', $name) => 'jnt_express',
            str_contains($name, 'flash') => 'flash_express',
            default => null,
        };
    }

    /**
     * A short destination code for sorting, printed big like courier labels do: province and city, three letters each
     * (e.g. "CEB-CEB" for Cebu City, Cebu; "LAG-CAL" for Calamba, Laguna).
     */
    public static function sortCode(array $recipient): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', (string) ($recipient['address'] ?? '')))));
        $city = $parts[count($parts) - 2] ?? ($parts[0] ?? '');
        $province = $parts[count($parts) - 1] ?? '';
        $code = fn (string $name) => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', preg_replace('/\b(city of|city|municipality of)\b/i', '', \Illuminate\Support\Str::ascii($name))), 0, 3));

        return trim(($code($province) ?: 'PH').'-'.($code($city) ?: 'XXX'), '-');
    }

    /** The tracking number as a QR code PNG (dompdf draws PNGs reliably), for phones that read QR faster than bars. */
    private static function qrCode(string $tracking): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::M,
            'scale' => 6,
            'quietzoneSize' => 2,
            'outputBase64' => true,
        ])))->render($tracking);
    }

    /** The waybill PDF, shown in the browser so it can be printed or saved. */
    public static function response(Shipment $shipment): Response
    {
        $waybill = self::for($shipment);

        return Pdf::loadView('waybill.pdf', ['waybill' => $waybill])
            ->setPaper(self::PAPER)
            ->stream('waybill-'.preg_replace('/[^A-Za-z0-9-]/', '', $waybill['tracking']).'.pdf');
    }

    /** The same label as a web page that opens the print dialog, with a link to the PDF. */
    public static function printView(Shipment $shipment, string $pdfUrl): \Illuminate\Contracts\View\View
    {
        return view('waybill.pdf', ['waybill' => self::for($shipment), 'web' => true, 'pdfUrl' => $pdfUrl]);
    }

    /** Deployed schema: the order's seller is a user with a shop profile. */
    private static function userSender(?User $seller): array
    {
        if (! $seller) {
            return ['name' => 'ZAYLO seller', 'phone' => null, 'address' => null];
        }
        $application = SellerApplication::where('user_id', $seller->id)->first();
        $address = $application?->formatted_address
            ?: Address::where('user_id', $seller->id)->orderByDesc('is_default')->oldest('id')->first()?->formatted();

        return ['name' => $seller->sellerStoreName(), 'phone' => $seller->phone, 'address' => $address ?: null];
    }

    /** Newer schema: a shop record with an owner and an optional pickup address. */
    private static function shopSender($shop): array
    {
        $owner = $shop?->owner;
        $address = $shop?->pickupAddress?->formatted()
            ?: ($owner ? SellerApplication::where('user_id', $owner->id)->first()?->formatted_address : null);

        return ['name' => $shop?->name ?? 'ZAYLO seller', 'phone' => $owner?->phone, 'address' => $address ?: null];
    }

    private static function recipient(?Order $order): array
    {
        $address = $order?->shipping_address;
        if (is_array($address)) {
            return [
                'name' => $order->recipient_name ?? data_get($address, 'recipient') ?? data_get($address, 'recipient_name') ?? 'Recipient',
                'phone' => $order->phone ?? data_get($address, 'phone'),
                'address' => collect(['line1', 'line2', 'barangay', 'city', 'province', 'postal_code'])
                    ->map(fn ($key) => data_get($address, $key))->filter()->implode(', ') ?: null,
            ];
        }

        return ['name' => $order?->recipient_name ?: 'Recipient', 'phone' => $order?->phone, 'address' => trim((string) $address) ?: null];
    }

    /** @return Collection<int, array{name: string, variant: ?string, sku: ?string, quantity: int, product_id: ?int}> */
    private static function items(?Order $order, $sellerOrder): Collection
    {
        $items = $sellerOrder ? $sellerOrder->items : ($order ? $order->items()->with('variant')->get() : collect());

        return $items->map(fn ($item) => [
            'name' => $name = (string) $item->product_name,
            // Checkout often saves the option into the item name already ("Shoe — Black / US 8"); don't repeat it.
            'variant' => (($variant = $item->variant_name ?? $item->variant?->name) && $variant !== 'Default' && ! str_contains($name, $variant)) ? $variant : null,
            'sku' => $item->sku ?? null,
            'quantity' => (int) $item->quantity,
            'product_id' => $item->product_id ? (int) $item->product_id : null,
        ])->values();
    }

    /** Total weight in grams, or null when any product has no weight set. */
    private static function weight(Collection $items): ?int
    {
        if ($items->isEmpty() || ! Schema::hasColumn('products', 'weight_grams')) {
            return null;
        }
        $weights = Product::whereIn('id', $items->pluck('product_id')->filter())->pluck('weight_grams', 'id');
        if ($items->contains(fn ($item) => blank($weights->get($item['product_id'])))) {
            return null;
        }

        return (int) $items->sum(fn ($item) => (int) $weights->get($item['product_id']) * $item['quantity']);
    }

    /** What the rider collects: the order total for unpaid cash on delivery, otherwise nothing. */
    private static function amountToCollect(?Order $order, $sellerOrder, Shipment $shipment): float
    {
        if (! $order || $order->payment_method !== 'cod') {
            return 0.0;
        }
        if (Schema::hasColumn('shipments', 'cod_amount_minor') && $shipment->cod_amount_minor !== null) {
            return $shipment->cod_amount_minor / 100;
        }
        if ($sellerOrder) {
            return ((int) $sellerOrder->subtotal_minor + (int) $sellerOrder->shipping_fee_minor) / 100;
        }
        $paid = $order->payment?->status === 'paid';

        return $paid ? 0.0 : (float) $order->total;
    }

    private static function paymentLabel(?Order $order): string
    {
        return match ($order?->payment_method) {
            'cod' => 'Cash on delivery',
            'gcash' => 'Paid online (GCash)',
            null => 'Prepaid',
            default => 'Paid online',
        };
    }
}
