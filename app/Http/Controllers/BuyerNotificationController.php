<?php

namespace App\Http\Controllers;

use App\Services\BuyerNotificationFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuyerNotificationController extends Controller
{
    public function index(Request $request, BuyerNotificationFeed $feed)
    {
        $notifications = $feed->forUser($request->user());

        return view('buyer.notifications', [
            'notifications' => $notifications,
            'unreadCount' => $notifications->where('read', false)->count(),
        ]);
    }

    public function open(Request $request, BuyerNotificationFeed $feed)
    {
        $key = $request->validate(['key' => ['required', 'string', 'max:100']])['key'];
        $notification = $feed->forUser($request->user())->firstWhere('key', $key);
        abort_unless($notification, 404);

        DB::table('buyer_notification_reads')->insertOrIgnore([
            'user_id' => $request->user()->id,
            'notification_key' => $key,
            'read_at' => now(),
        ]);

        return redirect()->to($notification['url']);
    }

    public function markAllRead(Request $request, BuyerNotificationFeed $feed)
    {
        $rows = $feed->forUser($request->user())
            ->where('read', false)
            ->map(fn ($notification) => [
                'user_id' => $request->user()->id,
                'notification_key' => $notification['key'],
                'read_at' => now(),
            ])->all();

        if ($rows) {
            collect($rows)->chunk(200)->each(fn ($chunk) => DB::table('buyer_notification_reads')->insertOrIgnore($chunk->all()));
        }

        return redirect()->route('buyer.notifications')->with('status', 'All notifications marked as read.');
    }
}
