<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Photos sent in Help & support chats. Like profile photos they are turned the right way up, re-encoded (which drops
 * metadata such as GPS location) and kept small, but they keep their shape and go on the private disk: only the people
 * in the conversation can open them (SupportChatController::photo).
 */
class ChatPhotos
{
    /** Longest side after resizing, in pixels. */
    public const MAX_SIDE = 1600;

    public const MAX_KB = 5120;

    /** Larger images would need too much memory to resize. */
    private const MAX_PIXELS = 40_000_000;

    public static function rules(): array
    {
        return ['photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KB]];
    }

    public static function messages(): array
    {
        return [
            'photo.mimes' => 'Send a JPG, PNG or WebP photo.',
            'photo.max' => 'The photo must be 5 MB or smaller.',
            'photo.uploaded' => 'The photo could not be uploaded. Photos must be 5 MB or smaller.',
        ];
    }

    /** Saves the photo under $folder and returns its path on the private disk. */
    public function store(UploadedFile $file, string $folder): string
    {
        $size = @getimagesize($file->getRealPath());
        if ($size && $size[0] * $size[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['photo' => 'This photo is too large. Use one under 40 megapixels.']);
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'This photo could not be read. Try a JPG or PNG.']);
        }
        $image = $this->upright($image, $file);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        // Transparent PNGs get a white background instead of black.
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // WebP needs GD built with libwebp; without it, JPEG keeps uploads working.
        $webp = function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false);
        ob_start();
        $webp ? imagewebp($canvas, null, 82) : imagejpeg($canvas, null, 85);
        $data = (string) ob_get_clean();

        $path = trim($folder, '/').'/'.Str::random(40).($webp ? '.webp' : '.jpg');
        // The disks do not throw, so a failed upload (say, to R2) must be caught here.
        if ($data === '' || ! Storage::disk('local')->put($path, $data)) {
            throw ValidationException::withMessages(['photo' => 'Your photo could not be sent right now. Please try again in a moment.']);
        }

        return $path;
    }

    /** Phone cameras save JPEGs sideways with a rotation tag; apply it. */
    private function upright(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $orientation = (int) ((@exif_read_data($file->getRealPath()) ?: [])['Orientation'] ?? 1);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
