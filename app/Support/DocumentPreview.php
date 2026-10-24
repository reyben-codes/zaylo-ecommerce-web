<?php

namespace App\Support;

/** How an uploaded document is shown in the document viewer modal (public/js/document-viewer.js). */
class DocumentPreview
{
    private const IMAGES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** "image", "pdf", or "file" for anything the browser cannot show inline. */
    public static function type(?string $path): string
    {
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        return match (true) {
            in_array($extension, self::IMAGES, true) => 'image',
            $extension === 'pdf' => 'pdf',
            default => 'file',
        };
    }
}
