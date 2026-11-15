<?php

namespace App\Http\Controllers;

use App\Services\AccountUpdates;
use Illuminate\Http\Request;

class AccountUpdateController extends Controller
{
    public function index(Request $request, AccountUpdates $updates)
    {
        $payload = $updates->payload($request->user());
        if (! $request->is('api/*')) {
            $payload['notification_count'] = $request->user()->hasRole('buyer')
                ? app(\App\Services\BuyerNotificationFeed::class)->forUser($request->user())->where('read', false)->count()
                : app(\App\Services\WorkspaceNotificationFeed::class)->unreadCount($request->user());
        }
        return response()->json($payload);
    }

    public function read(Request $request, AccountUpdates $updates)
    {
        $data = $request->validate(['section' => ['nullable', 'string', 'max:40'], 'through' => ['required', 'integer', 'min:0']]);
        $updates->read($request->user(), $data['section'] ?? null, $data['through']);
        return response()->json($updates->payload($request->user()));
    }
}
