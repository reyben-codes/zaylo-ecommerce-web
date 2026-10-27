<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uploaded public files (product, review and profile photos) at their saved "/storage/..." links. With Cloudflare
 * R2 configured the link redirects to the file there; otherwise the file is sent from storage/app/public, which
 * also covers servers without the public/storage symlink.
 */
class PublicFileController extends Controller
{
    public function __invoke(string $path): Response
    {
        abort_if($path === '' || str_contains($path, '..') || str_contains($path, '\\'), 404);
        $disk = Storage::disk('public');

        if (config('filesystems.disks.public.driver') === 's3') {
            // Not permanent: the R2 address may change (for example to a custom domain), so caches keep it a day.
            return redirect()->away($disk->url($path), 302)->header('Cache-Control', 'public, max-age=86400');
        }

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}
