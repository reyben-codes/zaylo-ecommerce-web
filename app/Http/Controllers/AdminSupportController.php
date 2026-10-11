<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The admin Chat page: the support inbox where staff answer riders. */
class AdminSupportController extends Controller
{
    public function index(Request $request)
    {
        $conversations = SupportConversation::with(['user', 'latestMessage'])
            ->whereNotNull('last_message_at')
            ->withCount(['messages as unread_count' => fn ($query) => $query->where('from_staff', false)->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->get();

        $selected = $request->filled('conversation')
            ? $conversations->firstWhere('id', (int) $request->input('conversation'))
            : $conversations->first();

        if ($selected) {
            $selected->markReadBy(staff: true);
            $selected->load(['messages.sender', 'user.riderApplication']);
        }

        return view('admin.chat', compact('conversations', 'selected'));
    }

    public function reply(Request $request, SupportConversation $conversation)
    {
        $validated = $request->validate(
            ['body' => ['required', 'string', 'max:2000'], 'client_id' => ['nullable', 'uuid']],
            ['body.required' => 'Type a reply to send.'],
        );

        $conversation->post($request->user(), $validated['body'], fromStaff: true, clientId: $validated['client_id'] ?? (string) Str::uuid());

        return redirect()->route('admin.chat', ['conversation' => $conversation->id])->with('status', 'Reply sent.');
    }
}
