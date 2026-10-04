<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['conversation' => 'nullable|integer|min:1', 'compact' => 'sometimes|boolean']);
        $conversation = isset($data['conversation'])
            ? $this->owned($request, $data['conversation'])->load(['buyer', 'seller', 'product'])
            : null;
        $sellerView = $request->routeIs('seller.chat');
        if ($request->boolean('compact')) {
            return view('chat.compact', compact('conversation', 'sellerView'));
        }

        return view($sellerView ? 'seller.chat' : 'buyer.chat', compact('conversation', 'sellerView'));
    }

    public function start(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);
        $seller = Product::usesLegacySchema() ? $product->seller : $product->seller?->owner;
        abort_unless($seller && $seller->isActive() && $seller->hasRole('seller') && $seller->hasVerifiedEmail(), 404);
        abort_if((int) $seller->id === (int) $request->user()->id, 422, 'You cannot message yourself.');

        $conversation = Conversation::firstOrCreate([
            'buyer_id' => $request->user()->id, 'seller_id' => $seller->id,
        ], ['product_id' => $product->id]);

        return redirect()->route('buyer.chat', ['conversation' => $conversation->id]);
    }

    public function inbox(Request $request)
    {
        $request->validate(['page' => 'nullable|integer|min:1']);
        $userId = (int) $request->user()->id;
        $conversations = Conversation::forUser($userId)
            ->with(['buyer', 'seller.sellerProfile', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('sender_id', '!=', $userId)->whereNull('read_at')])
            ->orderByDesc('last_message_at')->orderByDesc('id')->paginate(25);

        return response()->json([
            'conversations' => $conversations->map(function ($chat) use ($userId) {
                $other = $chat->buyer_id == $userId ? $chat->seller : $chat->buyer;
                $name = $chat->buyer_id == $userId
                    ? ($other->sellerProfile?->store_name ?? $other->sellerProfile?->name ?? $other->name)
                    : $other->name;

                return [
                    'id' => $chat->id, 'name' => $name,
                    'preview' => $chat->latestMessage?->body ?? 'Start the conversation',
                    'unread' => $chat->unread_count,
                    'updated_at' => $chat->last_message_at?->toIso8601String(),
                    'url' => route($chat->buyer_id == $userId ? 'buyer.chat' : 'seller.chat', ['conversation' => $chat->id]),
                ];
            }),
            'page' => $conversations->currentPage(), 'last_page' => $conversations->lastPage(),
        ]);
    }

    public function messages(Request $request, int $conversation)
    {
        $chat = $this->owned($request, $conversation);
        $data = $request->validate([
            'after' => 'nullable|integer|min:0|prohibits:before',
            'before' => 'nullable|integer|min:1|prohibits:after',
        ]);
        $query = $chat->messages();
        if (isset($data['after'])) {
            $messages = $query->where('id', '>', $data['after'])->oldest('id')->limit(100)->get();
        } else {
            $messages = $query->when(isset($data['before']), fn ($q) => $q->where('id', '<', $data['before']))
                ->latest('id')->limit(50)->get()->reverse()->values();
        }

        return response()->json([
            'messages' => $messages->map(fn ($message) => $this->messageData($message, (int) $request->user()->id)),
            'has_older' => $messages->isNotEmpty() && $chat->messages()->where('id', '<', $messages->first()->id)->exists(),
        ]);
    }

    public function send(Request $request, int $conversation)
    {
        $chat = $this->owned($request, $conversation);
        $request->merge(['body' => is_string($request->input('body')) ? trim($request->input('body')) : $request->input('body')]);
        $data = $request->validate(['body' => 'required|string|max:2000', 'client_id' => 'required|uuid']);
        $other = $chat->buyer_id == $request->user()->id ? $chat->seller : $chat->buyer;
        abort_unless($other->isActive() && $other->hasVerifiedEmail(), 422, 'This account is currently unavailable for messaging.');

        $message = DB::transaction(function () use ($chat, $request, $data) {
            // Serializing sends also keeps message IDs and inbox ordering consistent.
            $locked = Conversation::whereKey($chat->id)->lockForUpdate()->firstOrFail();
            $message = $locked->messages()->firstOrCreate([
                'sender_id' => $request->user()->id, 'client_id' => $data['client_id'],
            ], ['body' => $data['body']]);
            if ($message->wasRecentlyCreated) {
                $locked->update(['last_message_at' => $message->created_at]);
            }

            return $message;
        });

        return response()->json(['message' => $this->messageData($message, (int) $request->user()->id)]);
    }

    public function read(Request $request, int $conversation)
    {
        $chat = $this->owned($request, $conversation);
        $data = $request->validate(['through' => 'required|integer|min:1']);
        abort_unless($chat->messages()->whereKey($data['through'])->exists(), 422);
        $chat->messages()->where('id', '<=', $data['through'])
            ->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }

    private function owned(Request $request, int $id): Conversation
    {
        return Conversation::forUser((int) $request->user()->id)->findOrFail($id);
    }

    private function messageData(Message $message, int $userId): array
    {
        return [
            'id' => $message->id, 'body' => $message->body,
            'mine' => (int) $message->sender_id === $userId,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
