<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeding_refuses_production_without_creating_accounts(): void
    {
        $this->app['env'] = 'production';
        try {
            app(DatabaseSeeder::class)->run();
            $this->fail('Production demo seeding must refuse to run.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('disabled in production', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_production_deployment_rejects_ephemeral_uploads(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false, 'filesystems.disks.public.driver' => 'local', 'filesystems.disks.local.driver' => 'local']);
        $this->artisan('deployment:check')->expectsOutputToContain('Configure durable R2 storage')->assertFailed();
    }

    public function test_production_deployment_accepts_separate_configured_buckets_and_shared_cache(): void
    {
        $this->app['env'] = 'production';
        $disk = ['driver' => 's3', 'key' => 'fixture', 'secret' => 'fixture', 'endpoint' => 'https://fixture.r2.cloudflarestorage.com'];
        config([
            'app.debug' => false, 'cache.default' => 'database',
            'filesystems.disks.public' => $disk + ['bucket' => 'public-fixture', 'url' => 'https://images.example.test'],
            'filesystems.disks.local' => $disk + ['bucket' => 'private-fixture'],
        ]);
        $this->artisan('deployment:check')->assertSuccessful();
        config(['filesystems.disks.local.bucket' => 'public-fixture']);
        $this->artisan('deployment:check')->expectsOutputToContain('separate buckets')->assertFailed();
    }
}
