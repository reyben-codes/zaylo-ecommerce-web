<?php

namespace App\Services;

use App\Models\LogisticsProvider;
use App\Models\PendingRegistration;
use App\Models\Rider;
use App\Models\SellerApplication;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sign-ups wait here until the emailed code is entered; only then is the account created.
 * Unfinished sign-ups expire after a day, files included, and never reserve an email or mobile number.
 */
class PendingRegistrationService
{
    public const SESSION_KEY = 'pending_registration';
    public const LIFETIME_HOURS = 24;
    public const CODE_MINUTES = 5;
    public const MAX_ATTEMPTS = 5;

    public function __construct(private SellerApplicationService $sellerApplications, private LogisticsApplicationService $logisticsApplications) {}

    /** Upload fields any sign-up may send; only those validated for the chosen role are kept. */
    private static function documentFields(): array
    {
        return array_unique(array_merge(array_keys(SellerApplication::DOCUMENTS), array_keys(Rider::DOCUMENTS), array_keys(LogisticsProvider::DOCUMENTS)));
    }

    public function available(): bool
    {
        return Schema::hasTable('pending_registrations');
    }

    /** The sign-up this browser started, if it is still waiting. */
    public function current(Request $request): ?PendingRegistration
    {
        $token = $request->session()->get(self::SESSION_KEY);
        if (! is_string($token) || ! $this->available()) {
            return null;
        }
        $pending = PendingRegistration::where('token', $token)->where('expires_at', '>', now())->first();
        if (! $pending) {
            $request->session()->forget(self::SESSION_KEY);
        }

        return $pending;
    }

    /**
     * Keep validated sign-up details (password hashed) and park any uploads in a temporary folder.
     */
    public function begin(Request $request, array $validated, ?array $pickupNames): PendingRegistration
    {
        $this->prune();
        // Starting over with the same email replaces the earlier unfinished sign-up.
        PendingRegistration::where('email', $validated['email'])->get()->each(fn ($old) => $this->discard($old));

        $token = Str::random(64);
        $documents = [];
        foreach (self::documentFields() as $field) {
            if ($request->hasFile($field) && array_key_exists($field, $validated)) {
                $documents[$field] = $request->file($field)->store("pending-registrations/{$token}", 'local');
            }
        }

        $payload = collect($validated)->except(array_merge(['password', 'password_confirmation', 'terms'], array_keys($documents)))->all();
        $payload['password_hash'] = Hash::make($validated['password']);
        $payload['pickup_names'] = $pickupNames;

        return PendingRegistration::create([
            'token' => $token,
            'email' => $validated['email'],
            'role' => $validated['role'],
            'payload' => $payload,
            'documents' => $documents,
            'expires_at' => now()->addHours(self::LIFETIME_HOURS),
        ]);
    }

    /** Email a fresh 6-digit code; only the newest code works. */
    public function sendCode(PendingRegistration $pending, bool $first = false): void
    {
        if (! $first && $pending->code_sent_at && $pending->code_sent_at->copy()->addMinute()->isFuture()) {
            throw ValidationException::withMessages(['otp' => 'Please wait one minute before requesting another code.']);
        }

        DB::transaction(function () use ($pending) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $pending->forceFill([
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'code_expires_at' => now()->addMinutes(self::CODE_MINUTES),
                'code_sent_at' => now(),
            ])->save();

            // A send failure rolls the new code back, so the person can simply try again.
            Notification::route('mail', $pending->email)->notify(new VerifyEmailCode($code));
        });
    }

    /**
     * Check the code, then create the account and everything that belongs to it.
     */
    public function complete(PendingRegistration $pending, string $code): User
    {
        $error = DB::transaction(function () use ($pending, $code) {
            $locked = PendingRegistration::whereKey($pending->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->code_hash || ! $locked->code_expires_at || $locked->code_expires_at->lessThanOrEqualTo(now())) {
                return 'Your code has expired or is unavailable. Please request a new code.';
            }
            if ($locked->attempts >= self::MAX_ATTEMPTS) {
                return 'Too many incorrect attempts. Please request a new code.';
            }
            if (! Hash::check($code, $locked->code_hash)) {
                $locked->increment('attempts');

                return $locked->attempts >= self::MAX_ATTEMPTS
                    ? 'Too many incorrect attempts. Please request a new code.'
                    : 'That code is incorrect. Please check your email and try again.';
            }

            return null;
        });
        // Thrown outside the transaction so incorrect-attempt counts are kept.
        if ($error !== null) {
            throw ValidationException::withMessages(['otp' => $error]);
        }

        $this->ensureStillAvailable($pending);

        $user = DB::transaction(fn () => $this->createAccount($pending));
        PendingRegistration::whereKey($pending->id)->delete();
        Storage::disk('local')->deleteDirectory("pending-registrations/{$pending->token}");
        event(new Verified($user));

        return $user;
    }

    /**
     * Before the pending_registrations migration has run: create the account straight away, unverified,
     * as sign-up worked previously. Removed once every environment has migrated.
     */
    public function createImmediately(Request $request, array $validated, ?array $pickupNames): User
    {
        $pending = new PendingRegistration([
            'token' => Str::random(64),
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);
        $documents = [];
        foreach (self::documentFields() as $field) {
            if ($request->hasFile($field) && array_key_exists($field, $validated)) {
                $documents[$field] = $request->file($field)->store("pending-registrations/{$pending->token}", 'local');
            }
        }
        $pending->payload = collect($validated)->except(array_merge(['password', 'password_confirmation', 'terms'], array_keys($documents)))->all()
            + ['password_hash' => Hash::make($validated['password']), 'pickup_names' => $pickupNames];
        $pending->documents = $documents;

        $user = DB::transaction(fn () => $this->createAccount($pending));
        $user->forceFill(['email_verified_at' => null])->save();
        Storage::disk('local')->deleteDirectory("pending-registrations/{$pending->token}");

        return $user;
    }

    public function discard(PendingRegistration $pending): void
    {
        Storage::disk('local')->deleteDirectory("pending-registrations/{$pending->token}");
        $pending->delete();
    }

    /** Remove sign-ups nobody finished within a day. */
    public function prune(): int
    {
        if (! $this->available()) {
            return 0;
        }
        $expired = PendingRegistration::where('expires_at', '<=', now())->get();
        $expired->each(fn ($pending) => $this->discard($pending));

        return $expired->count();
    }

    /** Someone else may have taken the email, number, shop name, TIN, license or plate while this sign-up waited. */
    private function ensureStillAvailable(PendingRegistration $pending): void
    {
        $data = $pending->payload;
        $taken = match (true) {
            User::whereRaw('LOWER(email) = ?', [Str::lower($data['email'])])->exists() => 'This email address was registered by another account in the meantime.',
            User::where('phone', $data['phone'])->exists() => 'This mobile number was registered by another account in the meantime.',
            $pending->role === 'seller' && isset($data['shop_name']) && $this->sellerApplications->shopNameTaken($data['shop_name']) => 'Your shop name was taken by another seller in the meantime.',
            $pending->role === 'seller' && isset($data['tin']) && $this->sellerApplications->available() && SellerApplication::where('tin', $data['tin'])->exists() => 'Your TIN was registered by another seller in the meantime.',
            $pending->role === 'logistics' && isset($data['company_name']) && $this->logisticsApplications->companyNameTaken($data['company_name']) => 'Your company name was registered by another logistics partner in the meantime.',
            $pending->role === 'logistics' && isset($data['sec_registration_no']) && LogisticsProvider::where('sec_registration_no', $data['sec_registration_no'])->exists() => 'Your SEC registration number was registered by another logistics partner in the meantime.',
            $pending->role === 'logistics' && isset($data['tin']) && LogisticsProvider::where('tin', $data['tin'])->exists() => 'Your TIN was registered by another business in the meantime.',
            $pending->role === 'courier' && ! empty($data['license_number']) && Rider::where('license_number', $data['license_number'])->exists() => "Your driver's license number was registered by another rider in the meantime.",
            $pending->role === 'courier' && ! empty($data['plate_no']) && Rider::where('plate_no', $data['plate_no'])->exists() => 'Your plate number was registered by another rider in the meantime.',
            $pending->role === 'courier' && ! LogisticsProvider::acceptingRiders()->whereKey($data['logistics_provider_id'] ?? 0)->exists() => 'That logistics partner is no longer accepting riders.',
            default => null,
        };
        if ($taken) {
            $this->discard($pending);
            throw ValidationException::withMessages(['otp' => $taken.' Please sign up again.']);
        }
    }

    private function createAccount(PendingRegistration $pending): User
    {
        $data = $pending->payload;
        $role = $pending->role;

        $user = User::create([
            'name' => $data['first_name'].' '.$data['last_name'],
            'email' => $data['email'],
            // Hashed when the form was submitted; the model keeps an already-hashed value as is.
            'password' => $data['password_hash'],
            'role' => $role === 'courier' ? 'rider' : $role,
            'phone' => $data['phone'],
            'date_of_birth' => in_array($role, ['buyer', 'courier'], true) ? ($data['date_of_birth'] ?? null) : null,
            'status' => $role === 'buyer' ? 'active' : 'pending',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $documents = $this->moveDocuments($pending, match ($role) {
            'seller' => "seller-applications/{$user->id}",
            'logistics' => "logistics-applications/{$user->id}",
            default => "riders/{$user->id}",
        });

        if ($role === 'seller') {
            $applying = isset($data['shop_name']) && $this->sellerApplications->available();
            $user->openSellerShop($applying ? trim($data['shop_name']) : $user->name."'s Store");
            if ($applying) {
                $this->sellerApplications->save($user, null, $data, $data['pickup_names'] ?? [], null, $documents);
            }
        }

        if ($role === 'logistics' && isset($data['sec_registration_no']) && $this->logisticsApplications->available()) {
            $this->logisticsApplications->save($user, null, $data, $data['pickup_names'] ?? [], null, $documents);
        } elseif ($role === 'logistics') {
            LogisticsProvider::create([
                'user_id' => $user->id,
                'name' => $data['company_name'],
                'slug' => Str::slug($data['company_name']).'-'.Str::lower(Str::random(6)),
                'status' => 'pending',
                'contact_phone' => $data['phone'],
                'service_area' => $data['service_area'] ?? null,
            ]);
        }

        if ($role === 'courier') {
            $extra = Schema::hasColumn('riders', 'license_document_path') ? [
                'license_expires_on' => $data['license_expires_on'] ?? null,
                'license_document_path' => $documents['license_document'] ?? null,
                'or_cr_document_path' => $documents['or_cr_document'] ?? null,
            ] : [];
            Rider::create([
                'user_id' => $user->id,
                'logistics_provider_id' => $data['logistics_provider_id'],
                'vehicle_type' => $data['vehicle_type'],
                'plate_no' => $data['plate_no'] ?? null,
                'license_number' => $data['license_number'] ?? null,
                'status' => 'pending',
            ] + $extra);
        }

        return $user;
    }

    /** Move parked uploads into the new account's folder; returns field => final path. */
    private function moveDocuments(PendingRegistration $pending, string $folder): array
    {
        $moved = [];
        foreach ($pending->documents ?? [] as $field => $path) {
            if (! Storage::disk('local')->exists($path)) {
                continue;
            }
            $target = $folder.'/'.basename($path);
            Storage::disk('local')->move($path, $target);
            $moved[$field] = $target;
        }

        return $moved;
    }
}
