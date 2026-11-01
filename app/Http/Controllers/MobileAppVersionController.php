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

    /** For a release without notes of its own, such as those from before notes were written. */
    private const NOTES_UNKNOWN = ['Improvements and fixes.'];

    public function show(): JsonResponse
    {
        $release = $this->latestRelease();

        return response()->json([
            'latest_version' => $release['version'],
            'download_url' => config('marketplace.android_app.download_url'),
            'release_notes' => $release['notes'] ?: self::NOTES_UNKNOWN,
        ]);
    }

    /**
     * The latest release: "1.0.14" from its tag (null when it cannot be found) and its notes.
     *
     * @return array{version: ?string, notes: list<string>}
     */
    private function latestRelease(): array
    {
        if ($pinned = config('marketplace.android_app.latest_version')) {
            return ['version' => ltrim((string) $pinned, 'v'), 'notes' => []];
        }

        if ($cached = Cache::get('android-app:latest-release')) {
            return $cached;
        }

        $last = Cache::get('android-app:last-release');
        $release = $this->fetchFromGitHubApi();
        if (! $release && ($version = $this->fetchFromGitHubRedirect())) {
            // The redirect only tells the version, so notes are only kept if they belong to it.
            $release = ['version' => $version, 'notes' => $last['version'] === $version ? $last['notes'] : []];
        }
        if ($release) {
            Cache::put('android-app:latest-release', $release, now()->addMinutes(self::CACHE_MINUTES));
            Cache::forever('android-app:last-release', $release);
        }

        return $release ?? $last ?? ['version' => null, 'notes' => []];
    }

    /** @return array{version: string, notes: list<string>}|null */
    private function fetchFromGitHubApi(): ?array
    {
        try {
            $latest = Http::timeout(5)
                ->withUserAgent('Zaylo-Marketplace')
                ->acceptJson()
                ->get(config('marketplace.android_app.releases_api'))
                ->throw();
            $tag = $latest->json('tag_name');

            return is_string($tag) && preg_match('/^v?(\d+\.\d+\.\d+)$/', $tag, $match)
                ? ['version' => $match[1], 'notes' => $this->notesFrom((string) $latest->json('body'))]
                : null;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * The "- " lines of a release's description (.github/scripts/patch-notes.mjs).
     *
     * @return list<string>
     */
    private function notesFrom(string $body): array
    {
        preg_match_all('/^- (.+)$/m', str_replace("\r", '', $body), $matches);

        return array_map('trim', $matches[1]);
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
