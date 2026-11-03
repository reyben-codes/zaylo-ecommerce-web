<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use App\Services\ChatMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Help & support: every user can message the ZAYLO admin team, and admins can message any user.
 */
class SupportChatController extends Controller
{
    private const LAYOUTS = [
        'seller' => ['layouts.seller', []],
        'logistics' => ['layouts.workspace', ['workspace' => 'logistics']],
    ];

    /** Admin Center: all conversations, and start one with any user. */
    public function admin(Request $request)
    {
        if (! $this->available()) {
            return view('admin.setup-needed', ['title' => 'Messages']);
        }
        $threads = SupportThread::with(['user', 'latestMessage'])->orderByDesc('last_message_at')->orderByDesc('id')->get()
            ->each(function (SupportThread $thread) use ($request) {
                $thread->setAttribute('title', ($thread->user?->name ?? 'Deleted account').' · '.$this->roleLabel($thread->user));
                $thread->setAttribute('unread', $thread->unreadFor($request->user()));
            });
        $contacts = User::where('role', '!=', 'admin')->whereNotIn('id', $threads->pluck('user_id'))->orderBy('name')->limit(500)->get()
            ->map(fn (User $user) => ['id' => $user->id, 'label' => $user->name.' ('.$user->email.')', 'group' => str($this->roleLabel($user))->plural()->toString(), 'key' => 'user_id']);

        return view('admin.chat', $this->conversation($request, $threads, $contacts));
    }

    public function startFromAdmin(Request $request)
    {
        $data = $request->validate(['participant_id' => ['required', 'integer', 'exists:users,id']], ['participant_id.required' => 'Choose who to message.']);
        $user = User::findOrFail($data['participant_id']);
        abort_if($user->hasRole('admin'), 422, 'Choose a buyer, seller, rider or logistics partner.');

        return redirect()->route('admin.chat', ['thread' => SupportThread::firstOrCreate(['user_id' => $user->id])->id]);
    }

    /** Buyers, sellers, riders and logistics partners: their conversation with the admin team. */
    public function user(Request $request)
    {
        [$layout, $layoutData] = self::LAYOUTS[$request->user()->role] ?? ['layouts.app', []];
        if (! $this->available()) {
            return view('support.index', ['available' => false, 'layout' => $layout, 'layoutData' => $layoutData]);
        }
        $thread = SupportThread::firstOrCreate(['user_id' => $request->user()->id]);
        $thread->setAttribute('title', 'ZAYLO support team');
        $thread->setAttribute('unread', 0);
        $request->query->set('thread', $thread->id);

        return view('support.index', $this->conversation($request, collect([$thread]), collect()) + ['layout' => $layout, 'layoutData' => $layoutData]);
    }

    public function messages(Request $request, SupportThread $thread)
    {
        abort_unless($thread->isOpenTo($request->user()), 403);
        $messages = $thread->messages()->with('sender')->where('id', '>', (int) $request->query('after', 0))->orderBy('id')->limit(100)->get();
        $this->markRead($thread, $request->user());

        return response()->json(['messages' => $messages->map(fn ($message) => $this->payload($message, $request->user()))]);
    }

    public function send(Request $request, SupportThread $thread, ChatMedia $media)
    {
        abort_unless($thread->isOpenTo($request->user()), 403);
        $canAttach = ChatMedia::supported('support_messages');
        // A message is text, a photo or video, or both.
        $data = $request->validate(
            $canAttach ? ChatMedia::rules() : ['body' => ['required', 'string', 'max:2000']],
            ['body.required' => 'Type a message first.'] + ChatMedia::messages(),
        );
        $attachment = $canAttach ? $media->store($request, "support/{$thread->id}") : [];
        $message = DB::transaction(function () use ($thread, $request, $data, $attachment) {
            $message = $thread->messages()->create(['sender_id' => $request->user()->id, 'body' => trim((string) ($data['body'] ?? ''))]
                + $attachment);
            $thread->update(['last_message_at' => $message->created_at]);

            return $message;
        });

        return $request->expectsJson()
            ? response()->json(['message' => $this->payload($message->load('sender'), $request->user())], 201)
            : back();
    }

    /** A photo or video from a support conversation; only its user and the admin team can open it. */
    public function media(Request $request, SupportThread $thread, SupportMessage $message, string $kind)
    {
        abort_unless($thread->isOpenTo($request->user()) && (int) $message->support_thread_id === (int) $thread->id, 403);

        return ChatMedia::response($message, $kind);
    }

    private function conversation(Request $request, Collection $threads, Collection $contacts): array
    {
        $current = $threads->firstWhere('id', (int) $request->query('thread'));
        $messages = collect();
        if ($current) {
            $this->markRead($current, $request->user());
            $current->setAttribute('unread', 0);
            $messages = $current->messages()->with('sender')->orderByDesc('id')->limit(100)->get()->reverse()->values();
        }

        return [
            'available' => true,
            'threads' => $threads,
            'current' => $current,
            'messages' => $messages->map(fn ($message) => $this->payload($message, $request->user())),
            'contacts' => $contacts,
        ];
    }

    private function markRead(SupportThread $thread, User $reader): void
    {
        $thread->messages()->whereNull('read_at')
            ->when($reader->hasRole('admin'), fn ($q) => $q->where('sender_id', $thread->user_id), fn ($q) => $q->where('sender_id', '!=', $reader->id))
            ->update(['read_at' => now()]);
    }

    private function payload(SupportMessage $message, User $viewer): array
    {
        $fromAdmin = $message->sender?->hasRole('admin');

        return [
            'id' => $message->id,
            'body' => $message->body,
            'mine' => (int) $message->sender_id === (int) $viewer->id,
            // Users see one support team; admins see which admin answered.
            'sender' => $fromAdmin && ! $viewer->hasRole('admin') ? 'ZAYLO support' : $message->sender?->name,
            'time' => $message->created_at?->timezone('Asia/Manila')->format('M j, g:i A'),
        ] + ChatMedia::links($message, fn (string $kind) => route('support.media', [$message->support_thread_id, $message, $kind]));
    }

    private function roleLabel(?User $user): string
    {
        return AdminController::ROLE_LABELS[$user?->role === 'courier' ? 'rider' : $user?->role] ?? 'User';
    }

    private function available(): bool
    {
        return Schema::hasTable('support_threads') && Schema::hasTable('support_messages');
    }
}
