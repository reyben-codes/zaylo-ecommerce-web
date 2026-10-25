<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Profile photos uploaded on the website or in the app. Every upload is cropped to the square the user
 * framed (or a centered square), resized to SIZE pixels and saved as WebP on the public disk, which keeps
 * files small, turns phone photos the right way up and drops their metadata (such as GPS location).
 *
 * The crop arrives as a rectangle in fractions of the upright photo (crop_x, crop_y, crop_width,
 * crop_height, each 0 to 1), so it does not depend on the size the browser or app displayed it at.
 */
class ProfilePhotos
{
    public const SIZE = 512;

    public const MAX_KB = 5120;

    /** Larger images would need too much memory to resize. */
    private const MAX_PIXELS = 40_000_000;

    /** Validation rules for the uploaded `photo` and the optional crop the user framed. */
    public static function rules(): array
    {
        $fraction = ['nullable', 'required_with:crop_x,crop_y,crop_width,crop_height', 'numeric'];

        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KB, 'dimensions:min_width=64,min_height=64'],
            'crop_x' => [...$fraction, 'between:0,1'],
            'crop_y' => [...$fraction, 'between:0,1'],
            'crop_width' => [...$fraction, 'gt:0', 'max:1'],
            'crop_height' => [...$fraction, 'gt:0', 'max:1'],
        ];
    }

    /** The validated crop, or null for a centered square. */
    public static function cropFrom(array $validated): ?array
    {
        return isset($validated['crop_x'], $validated['crop_y'], $validated['crop_width'], $validated['crop_height'])
            ? array_map('floatval', [$validated['crop_x'], $validated['crop_y'], $validated['crop_width'], $validated['crop_height']])
            : null;
    }

    public static function messages(): array
    {
        return [
            'photo.required' => 'Choose a photo to upload.',
            'photo.mimes' => 'Use a JPG, PNG or WebP photo.',
            'photo.max' => 'The photo must be 5 MB or smaller.',
            'photo.dimensions' => 'The photo must be at least 64 × 64 pixels.',
            // Only a broken browser or app sends a bad crop; the user can just pick the photo again.
            'crop_*' => 'The photo could not be cropped. Please choose it again.',
        ];
    }

    /** @param  array{0: float, 1: float, 2: float, 3: float}|null  $crop  x, y, width, height as fractions */
    public function replace(User $user, UploadedFile $file, ?array $crop = null): void
    {
        $previous = $user->hasUploadedAvatar() ? $user->getRawOriginal('avatar') : null;
        $user->forceFill(['avatar' => $this->store($user, $file, $crop)])->save();

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }
    }

    /** Back to initials. A Google photo is not restored; signing in with Google again brings it back. */
    public function remove(User $user): void
    {
        $previous = $user->hasUploadedAvatar() ? $user->getRawOriginal('avatar') : null;
        $user->forceFill(['avatar' => null])->save();

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }
    }

    private function store(User $user, UploadedFile $file, ?array $crop): string
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

        [$left, $top, $side] = $this->square(imagesx($image), imagesy($image), $crop);
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        // Transparent PNGs get a white background instead of black.
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled($square, $image, 0, 0, $left, $top, self::SIZE, self::SIZE, $side, $side);

        // WebP needs GD built with libwebp; on a server without it, JPEG keeps uploads working.
        $webp = function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false);
        ob_start();
        $webp ? imagewebp($square, null, 82) : imagejpeg($square, null, 85);
        $data = (string) ob_get_clean();

        $path = "avatars/{$user->id}/".Str::random(40).($webp ? '.webp' : '.jpg');
        // The disks do not throw, so a failed upload (say, to R2) must be caught here, not saved as a missing photo.
        if ($data === '' || ! Storage::disk('public')->put($path, $data)) {
            throw ValidationException::withMessages(['photo' => 'Your photo could not be saved right now. Please try again in a moment.']);
        }

        return $path;
    }

    /**
     * The square to keep, in pixels: the user's crop (made square and kept inside the photo), or the
     * largest centered square.
     *
     * @return array{0: int, 1: int, 2: int} left, top, side
     */
    private function square(int $width, int $height, ?array $crop): array
    {
        $largest = min($width, $height);
        if ($crop === null) {
            return [intdiv($width - $largest, 2), intdiv($height - $largest, 2), $largest];
        }

        [$x, $y, $w, $h] = $crop;
        // Rounding in the browser or app can make the rectangle a pixel off square; keep its centre.
        $side = (int) round(max(1, min($w * $width, $h * $height, $largest)));
        $centerX = ($x + $w / 2) * $width;
        $centerY = ($y + $h / 2) * $height;
        $left = (int) round(min(max($centerX - $side / 2, 0), $width - $side));
        $top = (int) round(min(max($centerY - $side / 2, 0), $height - $side));

        return [$left, $top, $side];
    }

    /** Phone cameras save JPEGs sideways with a rotation tag; apply it before cropping. */
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
