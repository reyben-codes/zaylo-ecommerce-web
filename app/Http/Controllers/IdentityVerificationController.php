<?php

namespace App\Http\Controllers;

use App\Services\IdentityVerifications;
use Illuminate\Http\Request;

/**
 * Buyer side of ID verification: upload a government ID for an administrator to review. The rules live in
 * IdentityVerifications, shared with the mobile app.
 */
class IdentityVerificationController extends Controller
{
    public function store(Request $request, IdentityVerifications $verifications)
    {
        $verifications->ensureNotVerified($request->user());
        $data = $request->validate(IdentityVerifications::rules(), IdentityVerifications::messages());
        $verifications->submit($request->user(), $data['id_type'], $request->file('document'));

        return redirect()->to(route('buyer.account').'#identity-verification')
            ->with('status', 'ID uploaded. An administrator will review it soon; you can keep shopping meanwhile.');
    }
}
