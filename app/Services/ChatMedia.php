<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A photo or a video sent with a chat message, in any of the chats (buyer–seller, support, logistics, delivery).
 * Photos go through ChatPhotos (turned upright, re-encoded, kept small); videos are stored as sent. Both live on the
 * private disk: photos are served by each chat's own route, which checks who may see them, and so are videos on a
 * local disk. With private R2 storage, a video's link is a short-lived signed R2 URL instead, so phones and browsers
 * can stream and seek it straight from storage.
 */
class ChatMedia
{
    public const MAX_VIDEO_KB = 25600;

    /** Video types phones record and browsers play, with the extension each is saved under. */
    public const VIDEO_TYPES = [
        'video/mp4' => 'mp4',
        'video/x-m4v' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
        'video/3gpp' => '3gp',
    ];

    /** How long a signed video link works; long enough to watch, short enough that a shared link soon expires. */
    private const VIDEO_LINK_MINUTES = 180;

    public function __construct(private readonly ChatPhotos $photos) {}

    /** A message is text, a photo, a video, or text with one of them. */
    public static function rules(): array
    {
        return [
            'body' => ['required_without_all:photo,video', 'nullable', 'string', 'max:2000'],
            'photo' => [...ChatPhotos::rules()['photo'], 'prohibits:video'],
            'video' => ['nullable', 'file', 'mimetypes:'.implode(',', array_keys(self::VIDEO_TYPES)), 'max:'.self::MAX_VIDEO_KB],
        ];
    }

    public static function messages(): array
    {
        return ChatPhotos::messages() + [
            'body.required_without_all' => 'Type a message, or add a photo or video.',
            'photo.prohibits' => 'Send one photo or one video at a time.',
            'video.mimetypes' => 'Send an MP4, MOV, WebM or 3GP video.',
            'video.max' => 'The video must be 25 MB or smaller.',
            'video.uploaded' => 'The video could not be uploaded. Videos must be 25 MB or smaller.',
        ];
    }

    /** Whether [$table] has the photo and video columns (added by a migration the deployed database may not have yet). */
    public static function supported(string $table): bool
    {
        return Schema::hasColumn($table, 'image_path') && Schema::hasColumn($table, 'video_path');
    }

    /** Saves the request's photo or video under [$folder]: the message attributes to store, or none. */
    public function store(Request $request, string $folder): array
    {
        if ($request->hasFile('photo')) {
            return ['image_path' => $this->photos->store($request->file('photo'), $folder)];
        }
        if (! $request->hasFile('video')) {
            return [];
        }
        $file = $request->file('video');
        $extension = self::VIDEO_TYPES[$file->getMimeType()] ?? 'mp4';
        $path = Storage::disk('local')->putFileAs(trim($folder, '/'), $file, Str::random(40).'.'.$extension);
        // The disks do not throw, so a failed upload (say, to R2) must be caught here.
        if (! $path) {
            throw ValidationException::withMessages(['video' => 'Your video could not be sent right now. Please try again in a moment.']);
        }

        return ['video_path' => $path];
    }

    /**
     * The message's photo and video links. [$route] gives the chat's own link for 'photo' or 'video'.
     *
     * @param  callable(string): string  $route
     */
    public static function links(Model $message, callable $route): array
    {
        $video = $message->getAttribute('video_path');

        return [
            'photo' => $message->getAttribute('image_path') ? $route('photo') : null,
            'video' => $video ? (self::signsLinks() ? self::signedVideoLink($video) : $route('video')) : null,
        ];
    }

    /** The message's text for a conversation list: its body, or what was attached when it has none. */
    public static function preview(?Model $message, string $empty = ''): string
    {
        if (! $message) {
            return $empty;
        }
        if (filled($message->getAttribute('body'))) {
            return (string) $message->getAttribute('body');
        }

        return match (true) {
            (bool) $message->getAttribute('video_path') => '🎥 Video',
            (bool) $message->getAttribute('image_path') => '📷 Photo',
            default => $empty,
        };
    }

    /** Streams the message's photo or video. Range requests work on a local disk, so videos can be skipped through. */
    public static function response(Model $message, string $kind): Response
    {
        $path = $message->getAttribute($kind === 'video' ? 'video_path' : 'image_path');
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);
        $headers = ['Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff'];

        if ($kind === 'video') {
            if (self::signsLinks()) {
                return redirect()->away(self::signedVideoLink($path));
            }

            return response()->file($disk->path($path), $headers);
        }

        return $disk->response($path, 'photo.'.pathinfo($path, PATHINFO_EXTENSION), $headers, 'inline');
    }

    private static function signsLinks(): bool
    {
        return config('filesystems.disks.local.driver') === 's3';
    }

    private static function signedVideoLink(string $path): string
    {
        return Storage::disk('local')->temporaryUrl($path, now()->addMinutes(self::VIDEO_LINK_MINUTES));
    }
}
