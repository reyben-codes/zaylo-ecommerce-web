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

    public function ensureNotVerified(User $user): void
    {
        if ($user->isIdentityVerified()) {
            throw ValidationException::withMessages(['document' => 'Your ID is already verified.']);
        }
    }

    public function submit(User $user, string $idType, UploadedFile $document): IdentityVerification
    {
        $this->ensureNotVerified($user);

        // Private disk: ID documents are never reachable through a public URL.
        $path = $document->store("identity-documents/{$user->id}", 'local');

        return DB::transaction(function () use ($user, $idType, $document, $path) {
            // One open submission per buyer: a new upload replaces a pending or rejected one.
            $open = $user->identityVerifications()->whereIn('status', ['pending', 'rejected'])->latest()->first();
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
            $submission = $open ? tap($open)->update($attributes) : $user->identityVerifications()->create($attributes);

            if ($previousPath) {
                DB::afterCommit(fn () => Storage::disk('local')->delete($previousPath));
            }

            return $submission;
        });
    }
}
