<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Services\ChatStarter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    // A typing signal lasts a little longer than the client's ping interval so the indicator does not flicker.
    private const TYPING_SECONDS = 6;

    // How many of the most recent conversations the bubble's typing check covers.
    private const TYPING_STATUS_CONVERSATIONS = 30;

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

    public function start(Request $request, Product $product, ChatStarter $chats)
    {
        $conversation = $chats->forProduct($request->user(), $product);

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
                return [
                    'id' => $chat->id, 'name' => $this->partnerName($chat, $userId),
                    'preview' => $chat->latestMessage?->body ?? 'Start the conversation',
                    'unread' => $chat->unread_count,
                    'updated_at' => $chat->last_message_at?->toIso8601String(),
                    'url' => route($chat->buyer_id == $userId ? 'buyer.chat' : 'seller.chat', ['conversation' => $chat->id]),
                ];
            }),
            'page' => $conversations->currentPage(), 'last_page' => $conversations->lastPage(),
        ]);
    }

    /**
     * Who is typing to this account right now, across its recent conversations. The floating chat bubble
     * (website and mobile app) polls this to show typing dots while the chat itself is closed.
     */
    public function typingStatus(Request $request)
    {
        $userId = (int) $request->user()->id;
        $chats = Conversation::forUser($userId)->with(['buyer', 'seller.sellerProfile'])
            ->orderByDesc('last_message_at')->orderByDesc('id')->limit(self::TYPING_STATUS_CONVERSATIONS)->get();
        $keys = $chats->mapWithKeys(fn (Conversation $chat) => [$this->typingKey($chat, $this->otherParticipantId($chat, $userId)) => $chat]);
        $signals = $keys->isEmpty() ? [] : Cache::many($keys->keys()->all());

        return response()->json([
            'typing' => $keys->filter(fn (Conversation $chat, string $key) => ! empty($signals[$key]))->map(fn (Conversation $chat) => [
                'conversation_id' => $chat->id,
                'name' => $this->partnerName($chat, $userId),
                'url' => route($chat->buyer_id == $userId ? 'buyer.chat' : 'seller.chat', ['conversation' => $chat->id]),
            ])->values(),
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
            'typing' => Cache::has($this->typingKey($chat, $this->otherParticipantId($chat, (int) $request->user()->id))),
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

        Cache::forget($this->typingKey($chat, (int) $request->user()->id));

        return response()->json(['message' => $this->messageData($message, (int) $request->user()->id)]);
    }

    /**
     * Short-lived "is typing" signal, read by the other participant's message polling.
     */
    public function typing(Request $request, int $conversation)
    {
        $chat = $this->owned($request, $conversation);
        $typing = $request->validate(['typing' => 'required|boolean'])['typing'];
        $key = $this->typingKey($chat, (int) $request->user()->id);
        $typing ? Cache::put($key, true, now()->addSeconds(self::TYPING_SECONDS)) : Cache::forget($key);

        return response()->noContent();
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

    /** The other person as this account sees them: the shop's name for a buyer, the buyer's name for a seller. */
    private function partnerName(Conversation $chat, int $userId): string
    {
        $other = $chat->buyer_id == $userId ? $chat->seller : $chat->buyer;

        return (string) ($chat->buyer_id == $userId
            ? ($other?->sellerProfile?->store_name ?? $other?->sellerProfile?->name ?? $other?->name)
            : $other?->name);
    }

    private function typingKey(Conversation $chat, int $userId): string
    {
        return "chat-typing:{$chat->id}:{$userId}";
    }

    private function otherParticipantId(Conversation $chat, int $userId): int
    {
        return (int) ((int) $chat->buyer_id === $userId ? $chat->seller_id : $chat->buyer_id);
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
