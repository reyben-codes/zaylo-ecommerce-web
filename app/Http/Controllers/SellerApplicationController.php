<?php

namespace App\Http\Controllers;

use App\Services\SellerApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Where a seller waits for review, reads why an application was rejected, and corrects and resubmits it.
 */
class SellerApplicationController extends Controller
{
    public function __construct(private SellerApplicationService $applications) {}

    public function show(Request $request)
    {
        $user = $request->user();
        if ($user->isActive()) {
            return redirect()->route('seller.dashboard');
        }
        if ($user->status === 'suspended') {
            return $this->signOut($request, 'This seller account has been suspended.');
        }

        $application = $this->applications->available() ? $user->sellerApplication()->first() : null;

        return view('seller.application', [
            'user' => $user,
            'application' => $application,
            // Rejected sellers and sellers who signed up without the form (e.g. with Google) can edit and submit.
            'editable' => $user->status === 'rejected' || ! $application,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        abort_unless($this->applications->available(), 404);
        $application = $user->sellerApplication()->first();
        if ($user->isActive() || ($user->status === 'pending' && $application)) {
            return redirect()->route('seller.application');
        }
        abort_unless(in_array($user->status, ['pending', 'rejected'], true), 403);

        $data = $request->validate($this->applications->rules($request, $application), $this->applications->messages());
        $names = $this->applications->addressNames($data);

        DB::transaction(function () use ($user, $request, $data, $names, $application) {
            $this->applications->save($user, $request, $data, $names, $application);
            $user->update(['status' => 'pending', 'approved_at' => null, 'approved_by' => null]);
        });

        return redirect()->route('seller.application')
            ->with('status', 'Application submitted. We will email you once an administrator has reviewed it.');
    }

    private function signOut(Request $request, string $message)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('seller.login')->with('error', $message);
    }
}
