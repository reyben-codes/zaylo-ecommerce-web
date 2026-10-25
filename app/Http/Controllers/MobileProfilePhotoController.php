<?php

namespace App\Http\Controllers;

use App\Services\ProfilePhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Change or remove the signed-in buyer's or rider's profile photo from the app. */
class MobileProfilePhotoController extends Controller
{
    public function update(Request $request, ProfilePhotos $photos): JsonResponse
    {
        $validated = $request->validate(ProfilePhotos::rules(), ProfilePhotos::messages());
        $user = $request->user('mobile');
        $photos->replace($user, $request->file('photo'), ProfilePhotos::cropFrom($validated));

        return response()->json(['avatar_url' => $user->avatar_url]);
    }

    public function destroy(Request $request, ProfilePhotos $photos): JsonResponse
    {
        $photos->remove($request->user('mobile'));

        return response()->json(['avatar_url' => null]);
    }
}
