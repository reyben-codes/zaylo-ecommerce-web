<?php

namespace App\Http\Controllers;

use App\Models\LogisticsProvider;
use App\Models\LogisticsThread;
use App\Models\Product;
use App\Models\Rider;
use App\Models\User;
use App\Services\LogisticsMessenger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Chat between a logistics partner (sorting center) and its sellers and riders.
 */
class LogisticsChatController extends Controller
{
    public function __construct(private LogisticsMessenger $messenger) {}

    /** Logistics portal: every seller and rider conversation of this partner. */
    public function logistics(Request $request)
    {
        $provider = $request->user()->logisticsProvider;
        abort_unless($provider, 404);

        $threads = $this->threads(LogisticsThread::where('logistics_provider_id', $provider->id)->with('participant'), $request->user());
        $contacts = collect()
            ->merge($provider->riders()->where('status', 'approved')->with('user')->get()
                ->filter(fn (Rider $rider) => $rider->user)
                ->map(fn (Rider $rider) => ['id' => $rider->user_id, 'label' => $rider->user->name, 'group' => 'Riders', 'key' => 'user_id']))
            ->merge(User::where('role', 'seller')->where('status', 'active')->orderBy('name')->get()
                ->map(fn (User $seller) => ['id' => $seller->id, 'label' => $this->sellerName($seller), 'group' => 'Sellers', 'key' => 'user_id']));

        return view('logistics.messages', $this->conversation($request, $threads, $contacts) + ['provider' => $provider]);
    }

    public function startFromLogistics(Request $request)
    {
        $provider = $request->user()->logisticsProvider;
        abort_unless($provider, 404);
        $data = $request->validate(['participant_id' => ['required', 'integer']], ['participant_id.required' => 'Choose who to message.']);
        $participant = User::where('status', 'active')->find($data['participant_id']);
        $allowed = $participant && ($participant->hasRole('seller')
            || $provider->riders()->where('user_id', $participant->id)->where('status', 'approved')->exists());
        abort_unless($allowed, 422, 'You can message sellers and your own riders.');

        return redirect()->route('logistics.messages', ['thread' => $this->messenger->threadFor($provider, $participant)->id]);
    }

    /** Seller Center: conversations with logistics partners. */
    public function seller(Request $request)
    {
        $threads = $this->threads(LogisticsThread::where('user_id', $request->user()->id)->with('provider'), $request->user());
        $contacts = $this->sellerPartners()->map(fn (LogisticsProvider $provider) => ['id' => $provider->id, 'label' => $provider->name, 'group' => 'Logistics partners', 'key' => 'logistics_provider_id']);

        return view('seller.logistics-messages', $this->conversation($request, $threads, $contacts));
    }

    public function startFromSeller(Request $request)
    {
        $data = $request->validate(['provider_id' => ['required', Rule::in($this->sellerPartners()->pluck('id')->all())]], ['provider_id.required' => 'Choose a logistics partner.', 'provider_id.in' => 'Choose a logistics partner.']);

        return redirect()->route('seller.logistics-messages', ['thread' => $this->messenger->threadFor(LogisticsProvider::findOrFail($data['provider_id']), $request->user())->id]);
    }

    /** Rider portal: the one conversation with the rider's own logistics partner. */
    public function rider(Request $request)
    {
        $rider = Rider::where('user_id', $request->user()->id)->where('status', 'approved')->with('provider.owner')->first();
        $thread = $rider?->provider?->owner && LogisticsMessenger::available() ? $this->messenger->threadFor($rider->provider, $request->user()) : null;
        if ($thread) {
            $request->query->set('thread', $thread->id);
        }
        $threads = $thread ? $this->threads(LogisticsThread::whereKey($thread->id)->with('provider'), $request->user()) : collect();

        return view('courier.chat', $this->conversation($request, $threads, collect()) + ['rider' => $rider]);
    }

    /** New messages after the given id (the open page asks every few seconds). */
    public function messages(Request $request, LogisticsThread $thread)
    {
        abort_unless($thread->isOpenTo($request->user()), 403);
        $after = (int) $request->query('after', 0);
        $messages = $thread->messages()->with('sender')->where('id', '>', $after)->orderBy('id')->limit(100)->get();
        $this->messenger->markRead($thread, $request->user());

        return response()->json(['messages' => $messages->map(fn ($message) => $this->messenger->payload($message, $request->user()))]);
    }

    public function send(Request $request, LogisticsThread $thread)
    {
        abort_unless($thread->isOpenTo($request->user()), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']], ['body.required' => 'Type a message first.']);
        $message = $this->messenger->send($thread, $request->user(), trim($data['body']));

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->messenger->payload($message->load('sender'), $request->user())], 201);
        }

        return back();
    }

    private function threads($query, User $viewer): Collection
    {
        if (! LogisticsMessenger::available()) {
            return collect();
        }

        return $query->with('latestMessage')->orderByDesc('last_message_at')->orderByDesc('id')->get()
            ->each(function (LogisticsThread $thread) use ($viewer) {
                $thread->setAttribute('title', (int) $thread->user_id === (int) $viewer->id
                    ? ($thread->provider?->name ?? 'Logistics')
                    : $this->participantName($thread));
                $thread->setAttribute('unread', $thread->unreadFor($viewer));
            });
    }

    private function conversation(Request $request, Collection $threads, Collection $contacts): array
    {
        $current = $threads->firstWhere('id', (int) $request->query('thread')) ?? null;
        $messages = collect();
        if ($current) {
            $this->messenger->markRead($current, $request->user());
            $current->setAttribute('unread', 0);
            $messages = $current->messages()->with('sender')->orderByDesc('id')->limit(100)->get()->reverse()->values();
        }

        return [
            'available' => LogisticsMessenger::available(),
            'threads' => $threads,
            'current' => $current,
            'messages' => $messages->map(fn ($message) => $this->messenger->payload($message, $request->user())),
            // People already in a conversation are opened from the list instead.
            'contacts' => $contacts->reject(fn ($contact) => $threads->contains(fn (LogisticsThread $thread) => (int) $thread->{$contact['key']} === (int) $contact['id']))->values(),
        ];
    }

    private function participantName(LogisticsThread $thread): string
    {
        $user = $thread->participant;
        if (! $user) {
            return 'Deleted account';
        }

        return $user->hasRole('seller') ? $this->sellerName($user) : $user->name.' (rider)';
    }

    private function sellerName(User $seller): string
    {
        // Read the shop name without creating a shop (sellerShop() would on the newer schema).
        $store = Product::usesLegacySchema() ? $seller->sellerStoreName() : $seller->sellers()->oldest('id')->value('name');

        return $store && $store !== $seller->name ? $store.' · '.$seller->name : $seller->name;
    }

    private function sellerPartners(): Collection
    {
        return LogisticsProvider::where('status', 'approved')
            ->whereHas('owner', fn ($owner) => $owner->where('status', 'active'))
            ->orderBy('name')->get();
    }
}
