<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The buyer app's conversations with sellers. Reading and sending messages
 * uses the website's JSON chat endpoints (ChatController), so a thread looks
 * the same in the app, on the website, and in the Seller Center.
 */
class MobileChatController extends Controller
{
    public function inbox(Request $request): JsonResponse
    {
        $request->validate(['page' => 'nullable|integer|min:1']);
        $buyerId = (int) $request->user()->id;
        $conversations = Conversation::where('buyer_id', $buyerId)
            ->with(['seller.sellerProfile', 'product.images', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($query) => $query->where('sender_id', '!=', $buyerId)->whereNull('read_at')])
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'conversations' => collect($conversations->items())->map(fn (Conversation $chat) => $this->summary($chat, $buyerId))->values(),
            'page' => $conversations->currentPage(),
            'last_page' => $conversations->lastPage(),
        ]);
    }

    /** Seller messages the buyer has not read, for the app's badge. */
    public function unread(Request $request): JsonResponse
    {
        $buyerId = (int) $request->user()->id;

        return response()->json([
            'unread' => Message::whereHas('conversation', fn ($query) => $query->where('buyer_id', $buyerId))
                ->where('sender_id', '!=', $buyerId)->whereNull('read_at')->count(),
        ]);
    }

    /** Opens (or creates) the conversation with the seller of a product. */
    public function start(Request $request, Product $product): JsonResponse
    {
        $conversation = Conversation::startAbout($request->user(), $product)
            ->load(['seller.sellerProfile', 'product.images', 'latestMessage'])
            ->loadCount(['messages as unread_count' => fn ($query) => $query->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')]);

        return response()->json(['conversation' => $this->summary($conversation, (int) $request->user()->id)]);
    }

    private function summary(Conversation $chat, int $buyerId): array
    {
        $profile = $chat->seller?->sellerProfile;
        $latest = $chat->latestMessage;

        return [
            'id' => $chat->id,
            'name' => $profile?->store_name ?? $profile?->name ?? $chat->seller?->name ?? 'Seller',
            'product_name' => $chat->product?->name,
            'product_image_url' => $chat->product
                ? MobilePayload::imageUrl($chat->product->image_url ?: optional($chat->product->images->first())->path)
                : null,
            'preview' => $latest?->body,
            'last_message_mine' => $latest ? (int) $latest->sender_id === $buyerId : false,
            'unread' => (int) $chat->unread_count,
            'updated_at' => ($chat->last_message_at ?? $chat->created_at)?->toIso8601String(),
        ];
    }
}
