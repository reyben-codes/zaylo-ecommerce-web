<?php

namespace App\Http\Controllers;

use App\Models\PlatformPolicy;

/**
 * Public policy pages written by the admin in Platform settings. Until the admin writes them, the Terms of
 * Service and Privacy Policy show their built-in text (see PlatformPolicy::builtIn).
 */
class PolicyController extends Controller
{
    public function show(string $slug = 'terms')
    {
        abort_unless(isset(PlatformPolicy::PAGES[$slug]), 404);
        $policy = PlatformPolicy::published($slug);
        abort_unless($policy, 404);

        return view('legal.policy', ['policy' => $policy]);
    }
}
