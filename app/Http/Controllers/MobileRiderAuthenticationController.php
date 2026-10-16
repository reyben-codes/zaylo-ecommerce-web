<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\IssuesMobileTokens;
use App\Http\Requests\CheckRiderRegistrationRequest;
use App\Http\Requests\RegisterRiderRequest;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use App\Services\AddressBook;
use App\Services\PsgcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Rider sign-in and sign-up for the mobile app. Riders apply to a logistics
 * partner, which approves them from its Riders page on the website, exactly
 * like riders who sign up on the website. Logout, email verification and
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
        $rider = $user->rider()->with('provider')->first();
        $partner = $rider?->provider?->name ?? 'your logistics partner';

        return match ($rider?->status) {
            'pending' => "Your rider application is still under review by {$partner}.",
            'rejected' => "{$partner} did not approve your rider application"
                .($rider->rejection_reason ? ': '.$rider->rejection_reason : '.'),
            default => 'This rider account is suspended. Please contact '.$partner.' or ZAYLO support.',
        };
    }

    /** Logistics partners a new rider can apply to, for the sign-up form. */
    public function logisticsPartners(): JsonResponse
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
     * Creates a pending rider account and its application to the chosen
     * logistics partner. The partner approves it before the rider can sign in;
     * the email address is verified with a code the first time they do.
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

                // Same folder the website uses, so the logistics partner can open them.
                $folder = 'riders/'.$user->id;
                $documents = [];
                foreach (['license_image' => 'license_document_path', 'or_cr_image' => 'or_cr_document_path'] as $field => $column) {
                    if ($request->hasFile($field)) {
                        $storedPaths[] = $documents[$column] = $request->file($field)->store($folder, self::DOCUMENT_DISK);
                    }
                }

                Rider::create([
                    'user_id' => $user->id,
                    'logistics_provider_id' => $validated['logistics_provider_id'],
                    'vehicle_type' => $validated['vehicle_type'],
                    'plate_no' => $validated['plate_number'] ?? null,
                    'license_number' => $validated['license_number'] ?? null,
                    'license_expires_on' => $validated['license_expires_on'] ?? null,
                    'status' => 'pending',
                ] + $documents);

                return $user;
            });
        } catch (\Throwable $exception) {
            // The rows were rolled back; do not leave orphaned document photos.
            Storage::disk(self::DOCUMENT_DISK)->delete(array_filter($storedPaths));
            throw $exception;
        }

        $partner = LogisticsProvider::find($validated['logistics_provider_id'])?->name ?? 'The logistics partner';

        return response()->json([
            'email' => $user->email,
            'status' => 'pending',
            'message' => "Application submitted. {$partner} will review it before you can sign in.",
        ], 201);
    }
}
