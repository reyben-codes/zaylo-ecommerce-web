<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The rider app's chat with ZAYLO support. It is the rider's Help & support
 * thread from the website, so admins answer it from the same Admin Center inbox.
 */
class MobileRiderSupportController extends Controller
{
    /** The whole thread, oldest first; opening it marks support replies as read. */
    public function show(Request $request): JsonResponse
    {
        $thread = $this->thread($request->user());
        $this->unreadReplies($thread, $request->user())->update(['read_at' => now()]);

        return response()->json([
            'messages' => $thread->messages()->orderBy('id')->get()
                ->map(fn (SupportMessage $message) => $this->payload($message, $thread))->values(),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'client_id' => ['nullable', 'uuid'],
        ], ['body.required' => 'Type a message to send.']);

        $thread = $this->thread($request->user());
        $message = DB::transaction(function () use ($thread, $request, $validated) {
            $attributes = ['sender_id' => $request->user()->id, 'body' => trim($validated['body'])];
            // A send retried with the same client id after a dropped connection is stored once.
            $message = isset($validated['client_id'])
                ? $thread->messages()->firstOrCreate(['client_id' => $validated['client_id']], $attributes)
                : $thread->messages()->create($attributes);
            if ($message->wasRecentlyCreated) {
                $thread->update(['last_message_at' => $message->created_at]);
            }

            return $message;
        });

        return response()->json(['message' => $this->payload($message, $thread)], 201);
    }

    /** Unread support replies, for the badge on the Chat tab. */
    public function unread(Request $request): JsonResponse
    {
        $thread = SupportThread::where('user_id', $request->user()->id)->first();

        return response()->json(['unread' => $thread ? $this->unreadReplies($thread, $request->user())->count() : 0]);
    }

    private function thread(User $rider): SupportThread
    {
        return SupportThread::firstOrCreate(['user_id' => $rider->id]);
    }

    private function unreadReplies(SupportThread $thread, User $rider)
    {
        return $thread->messages()->whereNull('read_at')->where('sender_id', '!=', $rider->id);
    }

    private function payload(SupportMessage $message, SupportThread $thread): array
    {
        $fromStaff = (int) $message->sender_id !== (int) $thread->user_id;

        return [
            'id' => $message->id,
            // Messages sent from the website have no client id; the app only needs one that is unique.
            'client_id' => $message->client_id ?? 'server-'.$message->id,
            'body' => $message->body,
            'from_staff' => $fromStaff,
            'sender_name' => $fromStaff ? 'ZAYLO Support' : null,
            'read' => $message->read_at !== null,
            'sent_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
