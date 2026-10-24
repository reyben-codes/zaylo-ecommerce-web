<?php

namespace App\Http\Controllers;

use App\Services\ProfilePhotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Change or remove your profile photo from any account page on the website. */
class ProfilePhotoController extends Controller
{
    public function update(Request $request, ProfilePhotos $photos): RedirectResponse
    {
        $validated = $request->validate(ProfilePhotos::rules(), ProfilePhotos::messages());
        $photos->replace($request->user(), $request->file('photo'), ProfilePhotos::cropFrom($validated));

        return back()->with('status', 'Profile photo updated.');
    }

    public function destroy(Request $request, ProfilePhotos $photos): RedirectResponse
    {
        $photos->remove($request->user());

        return back()->with('status', 'Profile photo removed.');
    }
}
