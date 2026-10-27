<?php

namespace App\Http\Controllers;

use App\Exceptions\NoGoogleAccountException;
use App\Exceptions\WrongAccountTypeException;
use App\Http\Controllers\Concerns\IssuesMobileTokens;
use App\Http\Requests\MobileRiderRegistrationCheckRequest;
use App\Http\Requests\MobileRiderRegistrationRequest;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use App\Services\GoogleAccountService;
use App\Services\GoogleIdTokenVerifier;
use App\Services\PendingRegistrationService;
use App\Services\PsgcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Rider sign-in and sign-up for the mobile app. Riders apply to a logistics partner, which reviews them;
 * like every sign-up, the account is created once the emailed code is entered (MobileAuthenticationController).
 */
class MobileRiderAuthenticationController extends Controller
{
    use IssuesMobileTokens;

    public function login(Request $request): JsonResponse
    {
        return $this->signInWithRole($request, 'rider', 'This sign-in is for rider accounts. Buyers can use Log in instead.');
    }

    /**
     * "Continue with Google" for riders. Unlike buyers, it only signs in: a rider applies with the form (partner,
     * license, documents) first, then can use the Google account with the same email from then on.
     */
    public function google(Request $request, GoogleIdTokenVerifier $tokens, GoogleAccountService $accounts): JsonResponse
    {
        if (! $request->has('id_token')) {
            $token = $request->input('idToken') ?? $request->input('token') ?? $request->input('credential');
            if (is_string($token) && $token !== '') {
                $request->merge(['id_token' => $token]);
            }
        }

        $validated = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);
        if (! $tokens->configured()) {
            return response()->json(['message' => 'Google sign-in is not set up yet. Please use email and password for now.', 'code' => 'google_unconfigured'], 503);
        }

        try {
            $identity = $tokens->verify($validated['id_token']);
            if (! $identity) {
                throw ValidationException::withMessages(['google' => 'Google could not confirm this sign-in. Please try again.']);
            }
            $user = $accounts->resolve($identity, 'rider', createAccount: false);
        } catch (NoGoogleAccountException) {
            throw ValidationException::withMessages(['google' => 'No rider account uses this Google email. Register as a rider first, or sign in with your email and password.']);
        } catch (WrongAccountTypeException $exception) {
            throw ValidationException::withMessages(['google' => User::portalOf($exception->accountRole) === 'buyer'
                ? 'This Google email belongs to a buyer account. Use Log in as a buyer instead.'
                : 'This Google email belongs to an account that uses the ZAYLO website, not the rider app.']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            // Never log the token or Google's response; only what kind of failure it was.
            Log::warning('Rider Google sign-in failed.', ['exception_type' => $exception::class]);
            throw ValidationException::withMessages(['google' => 'We could not sign you in with Google. Please try again or use email and password.']);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['google' => $this->inactiveAccountMessage($user)]);
        }

        return $this->issueToken($user, $validated['device_name'] ?? null, 'Signed in with Google.');
    }

    /** Tells a rider whether their application is still in review, was declined, or is paused. */
    protected function inactiveAccountMessage(User $user): string
    {
        $rider = $user->rider;
        $partner = $rider?->provider?->name ?? 'your logistics partner';

        return match ($rider?->status) {
            'pending' => "Your rider application is still being reviewed by {$partner}.",
            'rejected' => "Your rider application was not approved by {$partner}".($rider->rejection_reason ? ': '.$rider->rejection_reason : '.'),
            'suspended' => "Your rider account was suspended by {$partner}".($rider->rejection_reason ? ': '.$rider->rejection_reason : '.'),
            default => 'This rider account is not active. Please contact ZAYLO support.',
        };
    }

    /** Logistics partners accepting riders, for the sign-up form. */
    public function partners(): JsonResponse
    {
        $partners = Schema::hasTable('logistics_providers')
            ? LogisticsProvider::acceptingRiders()->get(['id', 'name', 'service_area'])
            : collect();

        return response()->json([
            'partners' => $partners->map(fn (LogisticsProvider $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'service_area' => $partner->service_area,
            ])->values(),
            'vehicle_types' => Rider::VEHICLE_TYPES,
            // Vehicles that need no license, plate or OR/CR.
            'unlicensed_vehicle_types' => Rider::UNLICENSED_VEHICLES,
        ]);
    }

    /** Checks the text fields before the documents upload, including that the location exists. */
    public function checkRegistration(MobileRiderRegistrationCheckRequest $request, PsgcService $psgc): JsonResponse
    {
        $request->homeAddress($psgc);

        return response()->json(['valid' => true]);
    }

    public function register(MobileRiderRegistrationRequest $request, PsgcService $psgc, PendingRegistrationService $registrations): JsonResponse
    {
        // Resolve the home address before saving anything, so a location-service problem leaves nothing behind.
        $homeAddress = $request->homeAddress($psgc);
        $validated = $request->validated();

        // The website's field names, which the pending sign-up and account creation use.
        $files = [];
        foreach (['license_image' => 'license_document', 'or_cr_image' => 'or_cr_document'] as $from => $to) {
            if ($request->hasFile($from)) {
                $files[$to] = $validated[$to] = $request->file($from);
            }
        }
        $signUp = collect($validated)->only([
            'first_name', 'last_name', 'date_of_birth', 'gender', 'email', 'phone', 'password', 'password_confirmation',
            'logistics_provider_id', 'vehicle_type', 'license_number', 'license_expires_on', 'license_document', 'or_cr_document',
        ])->all() + [
            'role' => 'courier',
            'plate_no' => $validated['plate_number'] ?? null,
            'home_address' => $homeAddress,
        ];

        $partner = LogisticsProvider::find($validated['logistics_provider_id'])?->name ?? 'your logistics partner';

        return $this->startSignUp(
            $request,
            $registrations,
            $signUp,
            "Enter the code we emailed you to submit your application. {$partner} then reviews it.",
            $files,
        );
    }
}
