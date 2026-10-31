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
    /** How long a looked-up version is reused. */
    private const CACHE_MINUTES = 2;

    public function show(): JsonResponse
    {
        return response()->json([
            'latest_version' => $this->latestVersion(),
            'download_url' => config('marketplace.android_app.download_url'),
            'release_notes' => [
                'Floating Zay chatbot with animated expressions and sidebar toggle.',
                'Interactive pinch-to-zoom and full-screen photo zoom on products.',
                'Unified Cart experience across navigation, product cards, and checkout.',
                'Sidebar shortcut to summon the Zay chatbot and view patch notes.',
            ],
        ]);
    }

    /** "1.0.14" from the latest release tag, or null when it cannot be found. */
    private function latestVersion(): ?string
    {
        if ($pinned = config('marketplace.android_app.latest_version')) {
            return ltrim((string) $pinned, 'v');
        }

        if ($cached = Cache::get('android-app:latest-version')) {
            return $cached;
        }

        $version = $this->fetchFromGitHubApi() ?? $this->fetchFromGitHubRedirect();
        if ($version) {
            Cache::put('android-app:latest-version', $version, now()->addMinutes(self::CACHE_MINUTES));
            Cache::forever('android-app:last-version', $version);
        }

        return $version ?? Cache::get('android-app:last-version');
    }

    private function fetchFromGitHubApi(): ?string
    {
        try {
            $tag = Http::timeout(5)
                ->withUserAgent('Zaylo-Marketplace')
                ->acceptJson()
                ->get(config('marketplace.android_app.releases_api'))
                ->throw()
                ->json('tag_name');

            return is_string($tag) && preg_match('/^v?(\d+\.\d+\.\d+)$/', $tag, $match) ? $match[1] : null;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Fallback when api.github.com is rate-limited on a shared server IP:
     * github.com/.../releases/latest redirects (302) to .../releases/tag/vX.Y.Z.
     */
    private function fetchFromGitHubRedirect(): ?string
    {
        try {
            $downloadUrl = (string) config('marketplace.android_app.download_url');
            $latestPage = preg_replace('#/download/[^/]+$#', '', $downloadUrl);
            if (! $latestPage) {
                return null;
            }
            $location = Http::timeout(5)
                ->withUserAgent('Zaylo-Marketplace')
                ->withoutRedirecting()
                ->get($latestPage)
                ->header('Location');

            return is_string($location) && preg_match('#/tag/v?(\d+\.\d+\.\d+)$#', $location, $match) ? $match[1] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
