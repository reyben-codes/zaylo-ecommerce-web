<?php

namespace App\Http\Controllers;

use App\Models\MobilePushDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class MobilePushController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->status === 'active' && ($user->hasRole('buyer') || $user->hasRole('rider')), 403);
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096', 'regex:/^[A-Za-z0-9_:\-]+$/D'],
            'platform' => ['required', 'in:android'],
        ]);
        $session = $user->currentAccessToken();
        abort_unless($session, 401);
        DB::transaction(function () use ($session, $user, $data) {
            $currentUser = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($currentUser->status === 'active', 403);
            // Registration cannot race logout/password reset and recreate a revoked session's device.
            abort_unless($session->newQuery()->whereKey($session->id)->lockForUpdate()->exists(), 401);
            MobilePushDevice::upsert([[
                'mobile_token_id' => $session->id, 'user_id' => $user->id,
                'token' => Crypt::encryptString($data['token']), 'token_hash' => hash('sha256', $data['token']),
                'platform' => $data['platform'], 'created_at' => now(), 'updated_at' => now(),
            ]], ['mobile_token_id'], ['token', 'token_hash', 'platform', 'updated_at']);
        });

        return response()->json(['message' => 'Notification registration saved.']);
    }

    public function destroy(Request $request)
    {
        MobilePushDevice::where('mobile_token_id', $request->user()->currentAccessToken()?->id)
            ->where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Notification registration removed.']);
    }

    public function test(Request $request)
    {
        $sessionId = $request->user()->currentAccessToken()?->id;
        abort_unless(MobilePushDevice::active()->where('user_id', $request->user()->id)->where('mobile_token_id', $sessionId)->exists(), 422,
            'This phone is not registered with ZAYLO notifications yet. Reopen the app and try again.');
        app(\App\Services\MobilePushNotifications::class)->queue($request->user()->id, 'test-'.\Illuminate\Support\Str::uuid(),
            'ZAYLO notifications', 'Phone notifications are connected.', 'notifications', mobileTokenId: $sessionId);
        return response()->json(['message' => 'Test notification queued. Check your notification tray.'], 202);
    }
}
