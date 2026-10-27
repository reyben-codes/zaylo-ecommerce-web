<?php

namespace Tests\Feature;

use App\Support\MobilePayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploaded photos keep their "/storage/..." links wherever the files live: sent from storage/app/public on a
 * computer, or redirected to Cloudflare R2 on the hosted server.
 */
class UploadStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_r2_uploads_are_sent_from_the_server(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/7/photo.png', UploadedFile::fake()->image('photo.png', 20, 20)->getContent());

        $this->get('/storage/products/7/photo.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/storage/products/7/missing.png')->assertNotFound();
        $this->get('/storage/products/7/..%2F..%2F..%2F.env')->assertNotFound();
        $this->assertSame(url('/images/kettle.webp'), MobilePayload::imageUrl('/images/kettle.webp'));
    }

    public function test_with_r2_saved_links_and_app_links_point_at_the_bucket(): void
    {
        config(['filesystems.disks.public' => [
            'driver' => 's3', 'key' => 'key', 'secret' => 'secret', 'region' => 'auto', 'bucket' => 'zaylo-public',
            'endpoint' => 'https://account.r2.cloudflarestorage.com', 'url' => 'https://media.zaylo.test', 'use_path_style_endpoint' => true,
        ]]);
        Storage::forgetDisk('public');

        $this->get('/storage/products/7/photo.png')
            ->assertRedirect('https://media.zaylo.test/products/7/photo.png')
            ->assertHeader('Cache-Control', 'max-age=86400, public');
        $this->assertSame('https://media.zaylo.test/products/7/photo.png', MobilePayload::imageUrl('/storage/products/7/photo.png'));
        $this->assertSame('https://cdn.example.com/a.png', MobilePayload::imageUrl('https://cdn.example.com/a.png'));
    }

    public function test_the_copy_command_refuses_to_run_until_r2_is_configured(): void
    {
        $this->artisan('uploads:copy-to-r2')
            ->expectsOutputToContain('Add the R2_* settings to .env')
            ->assertFailed();
    }
}
