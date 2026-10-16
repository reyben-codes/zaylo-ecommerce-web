<?php

namespace App\Http\Controllers;

use App\Models\PlatformPolicy;

/**
 * Public policy pages written by the admin in Platform settings. Until the admin writes the Terms,
 * the built-in Terms page is shown.
 */
class PolicyController extends Controller
{
    public function show(string $slug = 'terms')
    {
        abort_unless(isset(PlatformPolicy::PAGES[$slug]), 404);
        $policy = PlatformPolicy::forSlug($slug);
        if (! $policy) {
            abort_unless($slug === 'terms', 404);

            return view('legal.terms');
        }

        return view('legal.policy', ['policy' => $policy]);
    }
}
