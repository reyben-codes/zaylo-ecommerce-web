<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The rider's chat with ZAYLO support. Staff reply from the admin Chat page. */
class MobileRiderSupportController extends Controller
{
    /** The whole thread, oldest first; opening it marks staff replies as read. */
    public function show(Request $request): JsonResponse
    {
        $conversation = $this->conversation($request->user());
        $conversation->markReadBy(staff: false);

        return response()->json([
            'messages' => $conversation->messages()->get()->map(fn (SupportMessage $message) => $this->payload($message))->values(),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'client_id' => ['nullable', 'uuid'],
        ], ['body.required' => 'Type a message to send.']);

        $message = $this->conversation($request->user())
            ->post($request->user(), $validated['body'], fromStaff: false, clientId: $validated['client_id'] ?? null);

        return response()->json(['message' => $this->payload($message)], 201);
    }

    /** Unread staff replies, for the badge on the Chat tab. */
    public function unread(Request $request): JsonResponse
    {
        $conversation = SupportConversation::where('user_id', $request->user()->id)->first();

        return response()->json(['unread' => $conversation?->unreadFor(staff: false) ?? 0]);
    }

    private function conversation(User $rider): SupportConversation
    {
        return SupportConversation::firstOrCreate(['user_id' => $rider->id]);
    }

    private function payload(SupportMessage $message): array
    {
        return [
            'id' => $message->id,
            'client_id' => $message->client_id,
            'body' => $message->body,
            'from_staff' => $message->from_staff,
            'sender_name' => $message->from_staff ? 'ZAYLO Support' : null,
            'read' => $message->read_at !== null,
            'sent_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
