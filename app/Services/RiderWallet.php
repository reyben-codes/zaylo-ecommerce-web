<?php

namespace App\Services;

use App\Models\RiderCommission;
use App\Models\RiderWithdrawal;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Rider wallets. Each prepaid parcel a rider delivers, and each COD parcel whose cash collection is confirmed, credits
 * the delivery rate saved with that order, funded from the logistics partner's receipts.
 * Riders withdraw to GCash from MIN_WITHDRAWAL up to their
 * balance; the amount leaves the balance at once, the rider's logistics partner sends it and records the GCash
 * reference, and a rejected request returns it. The balance is commissions less every withdrawal that was not rejected.
 */
class RiderWallet
{
    public const COMMISSION = Settlement::RIDER_SHIPPING_MINOR / 100;

    public const MIN_WITHDRAWAL = 100.00;

    public static function rateFor(User $rider): float
    {
        return $rider->rider?->provider?->riderRate() ?? self::COMMISSION;
    }

    public static function earningsFor(Shipment $shipment): float
    {
        // The order snapshot protects an agreed earning from subsequent company rate changes.
        $split = app(Settlement::class)->forShipment($shipment);

        return (float) $split['rider_earnings'];
    }

    /** A GCash mobile number: 11 digits starting with 09. */
    public const GCASH_PATTERN = '/^09[0-9]{9}$/D';

    /** Whether the database has wallets (after the 2026_11_07_000001 migration). */
    public static function ready(): bool
    {
        return Schema::hasTable('rider_commissions') && Schema::hasTable('rider_withdrawals');
    }

    /** Credits the rider for an eligible delivered parcel. A parcel is credited once, however often this runs. */
    public function credit(Shipment $shipment, User $rider): ?RiderCommission
    {
        if (! self::ready()) {
            return null;
        }

        $split = app(Settlement::class)->forShipment($shipment);
        if (($split['policy_version'] ?? 1) >= 2) {
            $order = $shipment->order ?: $shipment->sellerOrder?->order;
            if (ShipmentWorkflow::stageOf($shipment) !== 'delivered' || ($order?->payment_method === 'cod' && ! $shipment->cod_collected)) {
                return null;
            }
        }

        return RiderCommission::firstOrCreate(['shipment_id' => $shipment->id], [
            'user_id' => $rider->id,
            'logistics_provider_id' => Shipment::tracksProvider() ? $shipment->logistics_provider_id : null,
            'amount' => self::earningsFor($shipment),
        ]);
    }

    public function balance(User $rider): float
    {
        if (! self::ready()) {
            return 0.0;
        }
        $earned = (float) RiderCommission::where('user_id', $rider->id)->sum('amount');
        $withdrawn = (float) RiderWithdrawal::where('user_id', $rider->id)->where('status', '!=', RiderWithdrawal::REJECTED)->sum('amount');

        return round($earned - $withdrawn, 2);
    }

    /** @return array{balance:float, earned:float, pending:float, withdrawn:float, parcels:int} */
    public function summary(User $rider): array
    {
        if (! self::ready()) {
            return ['balance' => 0.0, 'earned' => 0.0, 'pending' => 0.0, 'withdrawn' => 0.0, 'parcels' => 0];
        }
        $commissions = RiderCommission::where('user_id', $rider->id);
        $withdrawals = fn (string $status) => round((float) RiderWithdrawal::where('user_id', $rider->id)->where('status', $status)->sum('amount'), 2);

        return [
            'balance' => $this->balance($rider),
            'earned' => round((float) (clone $commissions)->sum('amount'), 2),
            'pending' => $withdrawals(RiderWithdrawal::PENDING),
            'withdrawn' => $withdrawals(RiderWithdrawal::PAID),
            'parcels' => (clone $commissions)->count(),
        ];
    }

    /** @return Collection<int, RiderCommission> newest first */
    public function commissions(User $rider, int $limit = 30): Collection
    {
        return self::ready()
            ? RiderCommission::where('user_id', $rider->id)->with('shipment')->latest('id')->limit($limit)->get()
            : collect();
    }

    /** @return Collection<int, RiderWithdrawal> newest first */
    public function withdrawals(User $rider, int $limit = 30): Collection
    {
        return self::ready()
            ? RiderWithdrawal::where('user_id', $rider->id)->latest('id')->limit($limit)->get()
            : collect();
    }

    /** Whether riders can save a GCash account (after the 2026_11_07_000005 migration). */
    public static function savesAccounts(): bool
    {
        return Schema::hasColumn('riders', 'gcash_number');
    }

    /**
     * The GCash account withdrawals go to: the one the rider saved, or else the one they used last.
     *
     * @return array{number: string, name: string, saved: bool}|null
     */
    public function account(User $rider): ?array
    {
        $profile = $rider->rider;
        if (self::savesAccounts() && $profile?->gcash_number && $profile->gcash_name) {
            return ['number' => $profile->gcash_number, 'name' => $profile->gcash_name, 'saved' => true];
        }
        $last = self::ready() ? RiderWithdrawal::where('user_id', $rider->id)->latest('id')->first() : null;

        return $last ? ['number' => $last->gcash_number, 'name' => $last->gcash_name, 'saved' => false] : null;
    }

    /** Saves the GCash account the rider's withdrawals are sent to. */
    public function saveAccount(User $rider, string $number, string $name): array
    {
        abort_unless(self::savesAccounts(), 503, 'GCash accounts can be saved after the latest database update.');
        $profile = $rider->rider;
        abort_unless($profile, 404, 'No rider profile is linked to this account.');
        [$number, $name] = self::checkedAccount($number, $name);
        $profile->update(['gcash_number' => $number, 'gcash_name' => $name]);

        return ['number' => $number, 'name' => $name, 'saved' => true];
    }

    /**
     * Asks for [$amount] to be sent to GCash. Without a number and name (the app sends none once the rider saved an
     * account), it goes to the saved account.
     */
    public function withdraw(User $rider, float $amount, ?string $number = null, ?string $name = null): RiderWithdrawal
    {
        abort_unless(self::ready(), 503, 'Wallets are being set up. Please try again later.');
        $amount = round($amount, 2);
        $partnerId = $rider->rider?->logistics_provider_id;
        if (! $partnerId) {
            throw ValidationException::withMessages(['withdrawal' => 'Your account is not linked to a logistics partner, who sends withdrawals. Contact ZAYLO support.']);
        }
        if (blank($number) && blank($name)) {
            $saved = $this->account($rider);
            if (! ($saved['saved'] ?? false)) {
                throw ValidationException::withMessages(['gcash_number' => 'Set your GCash number and name in your account first.']);
            }
            ['number' => $number, 'name' => $name] = $saved;
        }
        [$number, $name] = self::checkedAccount((string) $number, (string) $name);
        if ($amount < self::MIN_WITHDRAWAL) {
            throw ValidationException::withMessages(['amount' => 'The minimum withdrawal is ₱'.number_format(self::MIN_WITHDRAWAL, 2).'.']);
        }

        return DB::transaction(function () use ($rider, $partnerId, $amount, $number, $name) {
            // Locked so two requests at once cannot both spend the same balance.
            User::whereKey($rider->id)->lockForUpdate()->firstOrFail();
            $balance = $this->balance($rider);
            if ($amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'You can withdraw up to ₱'.number_format($balance, 2).'.']);
            }

            return RiderWithdrawal::create([
                'user_id' => $rider->id, 'logistics_provider_id' => $partnerId, 'amount' => $amount, 'gcash_number' => $number, 'gcash_name' => $name,
                'status' => RiderWithdrawal::PENDING,
            ]);
        });
    }

    /**
     * A GCash number without spaces or dashes and a tidied name, or why they cannot be used.
     *
     * @return array{0: string, 1: string}
     */
    private static function checkedAccount(string $number, string $name): array
    {
        $number = preg_replace('/[\s-]+/', '', $number) ?? '';
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if (! preg_match(self::GCASH_PATTERN, $number)) {
            throw ValidationException::withMessages(['gcash_number' => 'Enter your 11-digit GCash number starting with 09.']);
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['gcash_name' => 'Enter the name on your GCash account.']);
        }

        return [$number, $name];
    }

    /** The logistics partner sent the money: records the GCash reference. */
    public function markPaid(RiderWithdrawal $withdrawal, User $processor, string $reference): RiderWithdrawal
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw ValidationException::withMessages(['reference' => 'Enter the GCash reference number of the transfer.']);
        }

        return $this->process($withdrawal, $processor, ['status' => RiderWithdrawal::PAID, 'reference' => $reference]);
    }

    /** The logistics partner turned the request down: the amount goes back to the rider's balance. */
    public function reject(RiderWithdrawal $withdrawal, User $processor, string $reason): RiderWithdrawal
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'Tell the rider why the withdrawal was rejected.']);
        }

        return $this->process($withdrawal, $processor, ['status' => RiderWithdrawal::REJECTED, 'rejection_reason' => $reason]);
    }

    private function process(RiderWithdrawal $withdrawal, User $processor, array $changes): RiderWithdrawal
    {
        return DB::transaction(function () use ($withdrawal, $processor, $changes) {
            // Locked so the request cannot be processed twice at once.
            $locked = RiderWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== RiderWithdrawal::PENDING) {
                throw ValidationException::withMessages(['withdrawal' => 'This withdrawal was already '.strtolower($locked->statusLabel()).'.']);
            }
            $locked->update($changes + ['processed_by' => $processor->id, 'processed_at' => now()]);

            return $locked;
        });
    }
}
