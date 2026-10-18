<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAddressRequest;
use App\Models\Address;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use App\Services\AddressBook;
use App\Services\BuyerNotificationFeed;
use App\Services\IdentityVerifications;
use App\Services\PsgcService;
use App\Support\MobilePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class MobileAccountController extends Controller
{
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new PersonName],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:11', new PhoneNumber],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
        ]);

        if ($user->google_id && $validated['email'] !== $user->email) {
            throw ValidationException::withMessages([
                'email' => 'The email address linked to Google cannot be changed here.',
            ]);
        }

        $emailChanged = $validated['email'] !== $user->email;
        $user->fill($validated);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        $message = 'Profile settings updated.';
        if ($emailChanged) {
            try {
                $user->sendEmailVerificationNotification();
                $message = 'Profile updated. Enter the code sent to your new email address.';
            } catch (\Throwable $exception) {
                report($exception);
                $message = 'Your email was updated, but we could not send a verification code. Please use Resend code.';
            }
        }

        return response()->json([
            'message' => $message,
            'email_verification_required' => $emailChanged,
            'user' => MobilePayload::user($user),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $hasPassword = filled($user->getAuthPassword());
        $validated = $request->validate([
            'current_password' => $hasPassword ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        // Sign out every other device; this phone keeps its session.
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->json([
            'message' => $hasPassword ? 'Password updated.' : 'Password created. You can now also sign in with email.',
            'user' => MobilePayload::user($user),
        ]);
    }

    /** ID verification status, as on the website's Profile Settings, plus the accepted ID types. */
    public function identity(Request $request): JsonResponse
    {
        return response()->json(['identity' => MobilePayload::identity($request->user())]);
    }

    public function submitIdentity(Request $request, IdentityVerifications $verifications): JsonResponse
    {
        abort_unless(Schema::hasTable('identity_verifications'), 503, 'ID verification is being set up. Please try again later.');
        $verifications->ensureNotVerified($request->user());
        $data = $request->validate(IdentityVerifications::rules(), IdentityVerifications::messages());
        $verifications->submit($request->user(), $data['id_type'], $request->file('document'));

        return response()->json([
            'message' => 'ID uploaded. An administrator will review it soon; you can keep shopping meanwhile.',
            'identity' => MobilePayload::identity($request->user()),
        ], 201);
    }

    public function addresses(Request $request): JsonResponse
    {
        return response()->json([
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get()
                ->map(fn (Address $address) => MobilePayload::address($address))->values(),
        ]);
    }

    public function storeAddress(SaveAddressRequest $request, PsgcService $psgc, AddressBook $book): JsonResponse
    {
        return $this->saveAddress($request, $psgc, $book, null, 201);
    }

    public function updateAddress(SaveAddressRequest $request, Address $address, PsgcService $psgc, AddressBook $book): JsonResponse
    {
        return $this->saveAddress($request, $psgc, $book, $address);
    }

    public function makeDefaultAddress(Request $request, Address $address, AddressBook $book): JsonResponse
    {
        $this->ownAddress($request, $address);
        $book->makeDefault($request->user(), $address);

        return $this->addresses($request);
    }

    public function destroyAddress(Request $request, Address $address, AddressBook $book): JsonResponse
    {
        $this->ownAddress($request, $address);
        $book->delete($request->user(), $address);

        return $this->addresses($request);
    }

    public function notifications(Request $request, BuyerNotificationFeed $feed): JsonResponse
    {
        $notifications = $feed->forUser($request->user());

        return response()->json([
            'unread_count' => $notifications->where('read', false)->count(),
            'notifications' => $notifications->map(fn (array $item) => [
                'key' => $item['key'],
                'type' => $item['type'],
                'title' => $item['title'],
                'message' => $item['message'],
                'action' => $item['action'],
                'at' => $item['at']->toIso8601String(),
                'read' => $item['read'],
                // Lets the app open the product screen for "New product" items.
                'product_slug' => $item['type'] === 'product' ? Str::afterLast($item['url'], '/') : null,
            ])->values(),
        ]);
    }

    public function markNotificationsRead(Request $request, BuyerNotificationFeed $feed): JsonResponse
    {
        $validated = $request->validate(['key' => ['nullable', 'string', 'max:100']]);
        $unread = $feed->forUser($request->user())->where('read', false)
            ->when($validated['key'] ?? null, fn ($items, $key) => $items->where('key', $key));

        $unread->map(fn ($notification) => [
            'user_id' => $request->user()->id,
            'notification_key' => $notification['key'],
            'read_at' => now(),
        ])->chunk(200)->each(fn ($chunk) => DB::table('buyer_notification_reads')->insertOrIgnore($chunk->values()->all()));

        return $this->notifications($request, $feed);
    }

    private function saveAddress(SaveAddressRequest $request, PsgcService $psgc, AddressBook $book, ?Address $address, int $status = 200): JsonResponse
    {
        try {
            $data = $request->addressData($psgc);
        } catch (ServiceUnavailableHttpException $exception) {
            throw ValidationException::withMessages(['region_code' => $exception->getMessage()]);
        }
        $saved = $book->save($request->user(), $data, $address);

        return response()->json(['message' => 'Address saved.', 'address' => MobilePayload::address($saved)], $status);
    }

    private function ownAddress(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403);
    }
}
