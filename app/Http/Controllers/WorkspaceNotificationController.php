<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceNotificationFeed;
use Illuminate\Http\Request;

/** The Admin Center and Seller Center notifications pages (admin.notifications, seller.notifications). */
class WorkspaceNotificationController extends Controller
{
    public function __construct(private WorkspaceNotificationFeed $feed) {}

    public function index(Request $request)
    {
        $notifications = $this->feed->forUser($request->user());

        return view('workspace.notifications', [
            'portal' => $this->portal($request),
            'notifications' => $notifications,
            'unreadCount' => $notifications->where('read', false)->count(),
        ]);
    }

    /** Marks one notification read and opens what it is about. */
    public function open(Request $request)
    {
        $key = $request->validate(['key' => ['required', 'string', 'max:100']])['key'];
        $notification = $this->feed->forUser($request->user())->firstWhere('key', $key);
        abort_unless($notification, 404);
        $this->feed->markRead($request->user(), collect([$key]));

        return redirect()->to($notification['url']);
    }

    public function markAllRead(Request $request)
    {
        $this->feed->markRead($request->user(), $this->feed->forUser($request->user())->where('read', false)->pluck('key'));

        return redirect()->route($this->portal($request).'.notifications')->with('status', 'All notifications marked as read.');
    }

    private function portal(Request $request): string
    {
        return $request->routeIs('admin.*') ? 'admin' : 'seller';
    }
}
