<?php

namespace App\Http\Controllers;

use App\Models\ComplianceWarning;

/** Seller Center: the seller confirms they read a policy warning from the admin. */
class ComplianceWarningController extends Controller
{
    public function acknowledge(ComplianceWarning $warning)
    {
        abort_unless((int) $warning->seller_id === (int) auth()->id(), 403);
        $warning->update(['acknowledged_at' => $warning->acknowledged_at ?? now()]);

        return back()->with('status', 'Warning marked as read.');
    }
}
