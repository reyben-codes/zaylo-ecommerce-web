<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The newest Android app, which the app compares with its own version when it opens and suggests updating. Every
 * change to the app on main publishes a release (.github/workflows/release-apk.yml); its tag is the version.
 */
class MobileAppVersionController extends Controller
{
    /** How long a looked-up version is reused; GitHub allows 60 unauthenticated lookups an hour. */
    private const CACHE_MINUTES = 10;

    public function show(): JsonResponse
    {
        return response()->json([
            'latest_version' => $this->latestVersion(),
            'download_url' => config('marketplace.android_app.download_url'),
        ]);
    }

    /** "1.0.4" from the latest release tag, or null when it cannot be found. */
    private function latestVersion(): ?string
    {
        if ($pinned = config('marketplace.android_app.latest_version')) {
            return ltrim((string) $pinned, 'v');
        }

        $version = Cache::remember('android-app:latest-version', now()->addMinutes(self::CACHE_MINUTES), function () {
            try {
                $tag = Http::timeout(5)->acceptJson()->get(config('marketplace.android_app.releases_api'))->throw()->json('tag_name');
            } catch (\Throwable $exception) {
                report($exception);

                return null;
            }

            return is_string($tag) && preg_match('/^v?(\d+\.\d+\.\d+)$/', $tag, $match) ? $match[1] : null;
        });
        // Keep answering with the last known version while GitHub is unreachable.
        if ($version) {
            Cache::forever('android-app:last-version', $version);
        }

        return $version ?? Cache::get('android-app:last-version');
    }
}
