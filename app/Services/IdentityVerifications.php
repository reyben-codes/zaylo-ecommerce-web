<?php

namespace App\Services;

use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Buyer ID uploads for administrator review, shared by the website and the mobile app.
 */
class IdentityVerifications
{
    public static function rules(): array
    {
        return [
            'id_type' => ['required', Rule::in(array_keys(IdentityVerification::ID_TYPES))],
            'document' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ];
    }

    public static function messages(): array
    {
        return [
            'document.required' => 'Choose a photo or PDF of your ID.',
            'document.mimes' => 'Upload a JPG, PNG, WebP, or PDF file.',
            'document.max' => 'The file must be 5 MB or smaller.',
        ];
    }

    public function submit(User $user, string $idType, UploadedFile $document): IdentityVerification
    {
        // Private disk: ID documents are never reachable through a public URL.
        $path = $document->store("identity-documents/{$user->id}", 'local');
        if (! $path) throw ValidationException::withMessages(['document' => 'The ID could not be saved. Please try again.']);

        try {
            $submission = DB::transaction(function () use ($user, $idType, $document, $path) {
                // Match admin review's lock order so a replacement cannot inherit an older approval.
                $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                // One open submission per buyer: a new upload replaces a pending or rejected one.
                $latest = $lockedUser->identityVerifications()->latest('id')->lockForUpdate()->first();
                $open = in_array($latest?->status, ['pending', 'rejected'], true) ? $latest : null;
                $previousPath = $open?->document_path;
                $attributes = [
                    'id_type' => $idType,
                    'document_path' => $path,
                    'original_name' => mb_substr($document->getClientOriginalName(), 0, 255),
                    'status' => 'pending',
                    'rejection_reason' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ];
                $submission = $open ? tap($open)->update($attributes) : $lockedUser->identityVerifications()->create($attributes);
                $lockedUser->forceFill(['identity_verified_at' => null])->save();

                if ($previousPath) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete($previousPath));
                }

                return $submission;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        $user->refresh();
        return $submission;
    }
}
