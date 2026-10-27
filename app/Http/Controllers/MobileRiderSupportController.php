<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The app's chat with ZAYLO support, for riders and buyers alike: the same support thread as the website's Help &
 * support page, answered by admins from Admin Center > Messages.
 */
class MobileRiderSupportController extends Controller
{
    /** The whole thread, oldest first; opening it marks the team's replies as read. */
    public function show(Request $request): JsonResponse
    {
        $rider = $request->user();
        $thread = $this->thread($rider);
        $thread->messages()->whereNull('read_at')->where('sender_id', '!=', $rider->id)->update(['read_at' => now()]);

        return response()->json([
            'messages' => $thread->messages()->with('sender')->orderBy('id')->limit(200)->get()
                ->map(fn (SupportMessage $message) => $this->payload($message, $rider))->values(),
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
            $message = isset($validated['client_id']) && Schema::hasColumn('support_messages', 'client_id')
                ? $thread->messages()->firstOrCreate(['client_id' => $validated['client_id']], $attributes)
                : $thread->messages()->create($attributes);
            if ($message->wasRecentlyCreated) {
                $thread->update(['last_message_at' => $message->created_at]);
            }

            return $message;
        });

        return response()->json(['message' => $this->payload($message->load('sender'), $request->user(), $validated['client_id'] ?? null)], 201);
    }

    /** Unread replies from the support team, for the badge on the Chat tab. */
    public function unread(Request $request): JsonResponse
    {
        $thread = $this->available() ? SupportThread::where('user_id', $request->user()->id)->first() : null;

        return response()->json([
            'unread' => $thread ? $thread->messages()->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)->count() : 0,
        ]);
    }

    private function thread(User $rider): SupportThread
    {
        abort_unless($this->available(), 503, 'Support chat is not available yet.');

        return SupportThread::firstOrCreate(['user_id' => $rider->id]);
    }

    private function payload(SupportMessage $message, User $rider, ?string $clientId = null): array
    {
        $fromStaff = (int) $message->sender_id !== (int) $rider->id;

        return [
            'id' => $message->id,
            // Messages sent from the website have no client id; the app only needs one that is unique.
            'client_id' => $clientId ?? $message->client_id ?? 'server-'.$message->id,
            // The app shows text only; a photo sent on the website is mentioned so the bubble is never blank.
            'body' => $message->image_path
                ? trim($message->body.' 📷 Photo (open ZAYLO on the web to view it)')
                : $message->body,
            'from_staff' => $fromStaff,
            'sender_name' => $fromStaff ? 'ZAYLO Support' : null,
            'read' => $message->read_at !== null,
            'sent_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function available(): bool
    {
        return Schema::hasTable('support_threads') && Schema::hasTable('support_messages');
    }
}
