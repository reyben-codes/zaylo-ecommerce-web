<?php

namespace App\Http\Controllers;

use App\Services\LogisticsApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Where a logistics partner waits for review, reads why an application was rejected, and corrects and resubmits it.
 */
class LogisticsApplicationController extends Controller
{
    public function __construct(private LogisticsApplicationService $applications) {}

    public function show(Request $request)
    {
        $user = $request->user();
        if ($user->isActive()) {
            return redirect()->route('logistics.dashboard');
        }
        if (! in_array($user->status, ['pending', 'rejected'], true)) {
            return $this->signOut($request, 'This logistics account has been suspended.');
        }

        $provider = $user->logisticsProvider;

        return view('logistics.application', [
            'user' => $user,
            'provider' => $provider,
            // Rejected partners, and partners who signed up before business details were collected, can edit and submit.
            'editable' => $user->status === 'rejected' || ! $provider?->hasApplication(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        abort_unless($this->applications->available(), 404);
        $provider = $user->logisticsProvider;
        if ($user->isActive() || ($user->status === 'pending' && $provider?->hasApplication())) {
            return redirect()->route('logistics.application');
        }
        abort_unless(in_array($user->status, ['pending', 'rejected'], true), 403);

        $this->applications->prepare($request);
        $data = $request->validate($this->applications->rules($provider), $this->applications->messages());
        $names = $this->applications->addressNames($data);

        DB::transaction(function () use ($user, $request, $data, $names, $provider) {
            $this->applications->save($user, $request, $data, $names, $provider);
            $user->update(['status' => 'pending', 'approved_at' => null, 'approved_by' => null]);
        });

        return redirect()->route('logistics.application')
            ->with('status', 'Application resubmitted. We will email you once an administrator has reviewed it.');
    }

    private function signOut(Request $request, string $message)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('logistics.login')->with('error', $message);
    }
}
