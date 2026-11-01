<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The newest app and its notes, as the "Update available" pop-up shows them.
 */
class MobileAppVersionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.android_app.latest_version' => null]);
    }

    public function test_the_notes_come_from_the_latest_release(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v1.0.25', 'body' => "- First change\r\n- Second change\n"])]);

        $this->getJson('/api/mobile/app-version')->assertOk()
            ->assertJsonPath('latest_version', '1.0.25')
            ->assertJsonPath('release_notes', ['First change', 'Second change']);
    }

    public function test_a_release_without_notes_claims_no_changes(): void
    {
        Http::fake(['api.github.com/*' => Http::response([
            'tag_name' => 'v1.0.24',
            'body' => '**Full Changelog**: https://github.com/example/compare/v1.0.23...v1.0.24',
        ])]);

        $this->getJson('/api/mobile/app-version')->assertOk()->assertJsonPath('release_notes', ['Improvements and fixes.']);
    }

    public function test_when_github_limits_the_api_only_the_right_versions_notes_are_kept(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['message' => 'rate limited'], 403),
            'github.com/*' => Http::response('', 302, ['Location' => 'https://github.com/example/releases/tag/v1.0.25']),
        ]);

        Cache::forever('android-app:last-release', ['version' => '1.0.25', 'notes' => ['Known change']]);
        $this->getJson('/api/mobile/app-version')->assertJsonPath('latest_version', '1.0.25')->assertJsonPath('release_notes', ['Known change']);

        Cache::flush();
        Cache::forever('android-app:last-release', ['version' => '1.0.24', 'notes' => ['Older change']]);
        $this->getJson('/api/mobile/app-version')->assertJsonPath('latest_version', '1.0.25')->assertJsonPath('release_notes', ['Improvements and fixes.']);
    }

    public function test_with_github_unreachable_the_last_known_release_is_used(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->getJson('/api/mobile/app-version')->assertOk()->assertJsonPath('latest_version', null);

        Cache::forever('android-app:last-release', ['version' => '1.0.24', 'notes' => ['Older change']]);
        $this->getJson('/api/mobile/app-version')->assertJsonPath('latest_version', '1.0.24')->assertJsonPath('release_notes', ['Older change']);
    }
}
