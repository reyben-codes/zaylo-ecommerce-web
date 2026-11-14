<?php

namespace App\Http\Controllers;

use App\Models\LogisticsMessage;
use App\Services\ChatMedia;
use App\Services\LogisticsMessenger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileRiderLogisticsChatController extends Controller
{
    private function thread(Request $request)
    {
        $provider = $request->user()->rider?->provider;
        abort_unless($provider, 404);
        return app(LogisticsMessenger::class)->threadFor($provider, $request->user());
    }

    public function index(Request $request)
    {
        $thread = $this->thread($request);
        app(LogisticsMessenger::class)->markRead($thread, $request->user());
        return response()->json(['messages' => $thread->messages()->latest('id')->limit(200)->get()->reverse()->values()
            ->map(fn ($message) => $this->payload($message, $request))]);
    }

    public function send(Request $request, ChatMedia $media)
    {
        $data = $request->validate(ChatMedia::rules() + ['client_id' => ['required', 'uuid']]);
        $thread = $this->thread($request);
        $message = DB::transaction(function () use ($thread, $request, $data, $media) {
            $thread->newQuery()->whereKey($thread->id)->lockForUpdate()->firstOrFail();
            $existing = $thread->messages()->where('client_id', $data['client_id'])->first();
            if ($existing) return $existing;
            $message = $thread->messages()->create([
                'client_id' => $data['client_id'], 'sender_id' => $request->user()->id,
                'body' => trim($data['body'] ?? ''),
            ] + $media->store($request, 'logistics/'.$thread->id));
            $thread->update(['last_message_at' => now()]);
            return $message;
        });
        return response()->json(['message' => $this->payload($message, $request)], 201);
    }

    public function media(Request $request, LogisticsMessage $message, string $kind)
    {
        abort_unless((int) $message->logistics_thread_id === (int) $this->thread($request)->id, 404);
        return ChatMedia::response($message, $kind);
    }

    private function payload(LogisticsMessage $message, Request $request): array
    {
        return [
            'id' => $message->id, 'client_id' => $message->client_id ?? 'logistics-'.$message->id,
            'body' => $message->body, 'from_staff' => (int) $message->sender_id !== (int) $request->user()->id,
            'sent_at' => $message->created_at->toIso8601String(),
        ] + ChatMedia::links($message, fn ($kind) => route('mobile.rider.logistics-chat.media', [$message, $kind]));
    }
}
