<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Buyer reviews of delivered purchases, shared by the website and the mobile app.
 */
class ProductReviews
{
    public const MAX_PHOTOS = 3;

    public static function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
        ];
    }

    /** Only the buyer who received the item may review it. */
    public function authorize(User $buyer, OrderItem $item): void
    {
        $order = $this->orderOf($item);
        abort_unless($order && (int) $order->buyer->id === (int) $buyer->id, 403);
        abort_unless($this->delivered($item), 403, 'Reviews are available after delivery.');
        abort_unless($item->product, 404);
    }

    /** Whether the item was delivered and can still be reviewed (no review yet). */
    public function canReview(OrderItem $item): bool
    {
        return $item->product_id !== null && $this->delivered($item) && ! $item->review;
    }

    /** @param UploadedFile[] $photos */
    public function publish(User $buyer, OrderItem $orderItem, int $rating, ?string $comment, array $photos): ProductReview
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($buyer, $orderItem, $rating, $comment, $photos, &$paths) {
                $item = OrderItem::whereKey($orderItem->id)->lockForUpdate()->firstOrFail();
                $this->authorize($buyer, $item);
                if ($item->review()->exists()) {
                    throw ValidationException::withMessages(['review' => 'You already reviewed this purchased item.']);
                }
                foreach ($photos as $photo) {
                    $path = $photo->store('reviews', 'public');
                    if (! $path) {
                        throw new \RuntimeException('Unable to store review photo.');
                    }
                    $paths[] = $path;
                }

                return ProductReview::create([
                    'order_item_id' => $item->id, 'product_id' => $item->product_id,
                    'user_id' => $buyer->id, 'rating' => $rating,
                    'comment' => $comment, 'photos' => $paths,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }
    }

    private function orderOf(OrderItem $item): ?Order
    {
        return Order::usesLegacySchema() ? $item->order : $item->sellerOrder?->order;
    }

    private function delivered(OrderItem $item): bool
    {
        $status = Order::usesLegacySchema() ? $item->order?->status : $item->sellerOrder?->status;

        return in_array($status, ['completed', 'delivered'], true);
    }
}
