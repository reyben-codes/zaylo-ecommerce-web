<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Services\ChatStarter;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile app's side of buyer–seller messages. The inbox, message list (with the other person's typing state),
 * sending, typing signals and read receipts are the website's own ChatController actions (see routes/api.php), so
 * a message sent in the app appears on the website and the other way round. This adds what the app needs besides:
 * starting a chat as JSON, the app's inbox, a conversation's header details, and the unread total for the badge.
 */
class MobileChatController extends Controller
{
    public function startForProduct(Request $request, Product $product, ChatStarter $chats): JsonResponse
    {
        return response()->json(['conversation' => $this->details($chats->forProduct($request->user(), $product), $request)]);
    }

    /** The app's Messages inbox: one row per seller conversation, most recent first. */
    public function inbox(Request $request): JsonResponse
    {
        $request->validate(['page' => 'nullable|integer|min:1']);
        $userId = (int) $request->user()->id;
        $conversations = Conversation::forUser($userId)
            ->with(['buyer', 'seller.sellerProfile', 'product.images', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($query) => $query->where('sender_id', '!=', $userId)->whereNull('read_at')])
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'conversations' => collect($conversations->items())->map(fn (Conversation $chat) => $this->summary($chat, $request))->values(),
            'page' => $conversations->currentPage(),
            'last_page' => $conversations->lastPage(),
        ]);
    }

    /** Opens (or creates) the conversation with a product's seller, as an inbox row. */
    public function startAboutProduct(Request $request, Product $product, ChatStarter $chats): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $chat = $chats->forProduct($request->user(), $product)
            ->loadCount(['messages as unread_count' => fn ($query) => $query->where('sender_id', '!=', $userId)->whereNull('read_at')]);

        return response()->json(['conversation' => $this->summary($chat, $request)]);
    }

    public function startForShop(Request $request, int $shop, ChatStarter $chats): JsonResponse
    {
        return response()->json(['conversation' => $this->details($chats->forShop($request->user(), $shop), $request)]);
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        $chat = Conversation::forUser((int) $request->user()->id)->findOrFail($conversation);

        return response()->json(['conversation' => $this->details($chat, $request)]);
    }

    /** Messages from others the buyer has not read yet, across every conversation. */
    public function unread(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $unread = Schema::hasTable('messages')
            ? Message::whereNull('read_at')->where('sender_id', '!=', $userId)
                ->whereIn('conversation_id', Conversation::forUser($userId)->select('id'))->count()
            : 0;

        return response()->json(['unread' => $unread]);
    }

    /** An inbox row: who it is with, the product it began from, the latest message and the unread count. */
    private function summary(Conversation $chat, Request $request): array
    {
        $chat->loadMissing('latestMessage');
        $details = $this->details($chat, $request);
        $latest = $chat->latestMessage;

        return [
            'id' => $chat->id,
            'name' => $details['name'],
            'product_name' => $details['product']['name'] ?? null,
            'product_image_url' => $details['product']['image_url'] ?? null,
            'preview' => $latest?->body,
            'last_message_mine' => $latest ? (int) $latest->sender_id === (int) $request->user()->id : false,
            'unread' => (int) ($chat->unread_count ?? 0),
            'updated_at' => ($chat->last_message_at ?? $chat->created_at)?->toIso8601String(),
        ];
    }

    /** Who the buyer is talking to (shop name, as in the website's inbox) and the product the chat began from. */
    private function details(Conversation $chat, Request $request): array
    {
        $chat->loadMissing(['buyer', 'seller.sellerProfile', 'product.images']);
        $buyerSide = (int) $chat->buyer_id === (int) $request->user()->id;
        $other = $buyerSide ? $chat->seller : $chat->buyer;
        $name = $buyerSide
            ? ($other?->sellerProfile?->store_name ?? $other?->sellerProfile?->name ?? $other?->name)
            : $other?->name;

        return [
            'id' => $chat->id,
            'name' => $name ?? 'ZAYLO user',
            'product' => $chat->product ? [
                'slug' => $chat->product->slug,
                'name' => $chat->product->name,
                'image_url' => MobilePayload::imageUrl($chat->product->image_url ?: $chat->product->images->first()?->path),
            ] : null,
        ];
    }
}
