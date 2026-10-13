<?php

namespace App\Http\Controllers;

use App\Models\IdentityVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Buyer side of ID verification: upload a government ID for an administrator to review.
 */
class IdentityVerificationController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->isIdentityVerified()) {
            throw ValidationException::withMessages(['document' => 'Your ID is already verified.']);
        }

        $data = $request->validate([
            'id_type' => ['required', Rule::in(array_keys(IdentityVerification::ID_TYPES))],
            'document' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [
            'document.required' => 'Choose a photo or PDF of your ID.',
            'document.mimes' => 'Upload a JPG, PNG, WebP, or PDF file.',
            'document.max' => 'The file must be 5 MB or smaller.',
        ]);

        // Private disk: ID documents are never reachable through a public URL.
        $path = $request->file('document')->store("identity-documents/{$user->id}", 'local');

        DB::transaction(function () use ($user, $data, $path, $request) {
            // One open submission per buyer: a new upload replaces a pending or rejected one.
            $open = $user->identityVerifications()->whereIn('status', ['pending', 'rejected'])->latest()->first();
            $previousPath = $open?->document_path;
            $attributes = [
                'id_type' => $data['id_type'],
                'document_path' => $path,
                'original_name' => mb_substr($request->file('document')->getClientOriginalName(), 0, 255),
                'status' => 'pending',
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ];
            $open ? $open->update($attributes) : $user->identityVerifications()->create($attributes);

            if ($previousPath) {
                DB::afterCommit(fn () => Storage::disk('local')->delete($previousPath));
            }
        });

        return redirect()->to(route('buyer.account').'#identity-verification')
            ->with('status', 'ID uploaded. An administrator will review it soon; you can keep shopping meanwhile.');
    }
}
