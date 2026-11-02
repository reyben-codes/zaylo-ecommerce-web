<?php

namespace App\Http\Controllers;

use App\Services\ShopCoverPhotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Change or remove the cover photo on the seller's shop page. */
class ShopCoverController extends Controller
{
    public function update(Request $request, ShopCoverPhotos $covers): RedirectResponse
    {
        $request->validate(ShopCoverPhotos::rules(), ShopCoverPhotos::messages());
        $covers->replace($request->user(), $request->file('cover'));

        return back()->with('status', 'Cover photo updated.');
    }

    public function destroy(Request $request, ShopCoverPhotos $covers): RedirectResponse
    {
        $covers->remove($request->user());

        return back()->with('status', 'Cover photo removed.');
    }
}
