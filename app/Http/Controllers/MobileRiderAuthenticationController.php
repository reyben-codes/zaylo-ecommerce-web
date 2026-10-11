<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\IssuesMobileTokens;
use App\Http\Requests\CheckRiderRegistrationRequest;
use App\Http\Requests\RegisterRiderRequest;
use App\Models\RiderApplication;
use App\Models\User;
use App\Services\AddressBook;
use App\Services\PsgcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Rider sign-in and sign-up for the mobile app. Logout, email verification and
 * password reset are shared with buyers and stay on MobileAuthenticationController.
 */
class MobileRiderAuthenticationController extends Controller
{
    use IssuesMobileTokens;

    /** Private disk: licenses and OR/CRs must never be publicly reachable. */
    private const DOCUMENT_DISK = 'local';

    public function login(Request $request): JsonResponse
    {
        return $this->signInWithRole($request, 'rider', 'This sign-in is for rider accounts. Buyers can use Log in instead.');
    }

    /** Tells a rider whether their application is still in review or was declined. */
    protected function inactiveAccountMessage(User $user): string
    {
        $application = $user->riderApplication;

        return match ($application?->status) {
            RiderApplication::STATUS_PENDING => 'Your rider application is still under review. Please allow up to 24 hours.',
            RiderApplication::STATUS_REJECTED => 'Your rider application was not approved'
                .($application->rejection_reason ? ': '.$application->rejection_reason : '.'),
            default => 'This rider account is suspended. Please contact ZAYLO support.',
        };
    }

    /**
     * Validates the text fields without uploads, so the app can report problems
     * (such as an email or plate number already registered) before uploading.
     */
    public function checkRegistration(CheckRiderRegistrationRequest $request, PsgcService $psgc): JsonResponse
    {
        // Confirms the barangay belongs to the city, the city to the province, and so on.
        $psgc->addressNames([
            'region_code' => $request->validated('region_code'),
            'province_code' => $request->validated('province_code'),
            'city_municipality_code' => $request->validated('city_municipality_code'),
            'barangay_code' => $request->validated('barangay_code'),
        ]);

        return response()->json(['valid' => true]);
    }

    /**
     * Creates a pending rider account plus its application. An administrator
     * reviews it (about 24 hours) before the account is activated. The email
     * address is verified with a code the first time the approved rider signs in.
     */
    public function register(RegisterRiderRequest $request, PsgcService $psgc, AddressBook $addresses): JsonResponse
    {
        $validated = $request->validated();
        $storedPaths = [];

        try {
            $user = DB::transaction(function () use ($request, $validated, $psgc, $addresses, &$storedPaths) {
                $user = User::create([
                    'name' => $validated['first_name'].' '.$validated['last_name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => 'rider',
                    'phone' => $validated['phone'],
                    'date_of_birth' => $validated['date_of_birth'],
                    'gender' => $validated['gender'],
                    'status' => 'pending',
                ]);
                // Throws a validation error (and rolls back) if the location does not exist.
                $addresses->save($user, RegisterRiderRequest::riderAddressData($validated, $user, $psgc));

                $folder = 'rider-documents/'.$user->id;
                $storedPaths[] = $licensePath = $request->file('license_image')->store($folder, self::DOCUMENT_DISK);
                $storedPaths[] = $orCrPath = $request->file('or_cr_image')->store($folder, self::DOCUMENT_DISK);

                $user->riderApplication()->create([
                    'license_number' => $validated['license_number'],
                    'license_expires_on' => $validated['license_expires_on'],
                    'license_image_path' => $licensePath,
                    'vehicle_type' => $validated['vehicle_type'],
                    'plate_number' => $validated['plate_number'],
                    'or_cr_image_path' => $orCrPath,
                    'terms_accepted_at' => now(),
                    'terms_version' => RiderApplication::TERMS_VERSION,
                ]);

                return $user;
            });
        } catch (\Throwable $exception) {
            // The rows were rolled back; do not leave orphaned document photos.
            Storage::disk(self::DOCUMENT_DISK)->delete(array_filter($storedPaths));
            throw $exception;
        }

        return response()->json([
            'email' => $user->email,
            'status' => 'pending',
            'message' => 'Application submitted. Please wait 24 hours while we review your application.',
        ], 201);
    }
}
