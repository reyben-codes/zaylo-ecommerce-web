<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Services\ReturnPickups;
use App\Services\SellerQueues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Proof photos of a returned item (at pickup and at the hub), for the people in the case, its pickup rider, and the
 * logistics partner handling the return.
 */
class ReturnPhotoController extends Controller
{
    public function show(Request $request, Dispute $dispute, string $kind, ReturnPickups $returns, SellerQueues $sellers)
    {
        $user = $request->user();
        $allowed = $dispute->isOpenTo($user)
            // The same returns the seller's Returns page lists.
            || ($user->hasRole('seller') && $sellers->returns($user)->whereKey($dispute->id)->exists())
            || (Dispute::tracksReturnRiders() && (int) $dispute->return_rider_id === (int) $user->id)
            || ($user->hasRole('logistics') && $user->logisticsProvider && $returns->handledBy($dispute, $user->logisticsProvider));
        abort_unless($allowed, 403);

        $path = $dispute->returnPhotos()[$kind] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
