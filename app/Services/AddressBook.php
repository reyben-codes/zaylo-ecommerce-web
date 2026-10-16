<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saved-address mutations shared by the web account pages and the mobile API.
 * Every write locks the owning user so only one default address can exist.
 */
class AddressBook
{
    public function save(User $owner, array $data, ?Address $address = null): Address
    {
        return DB::transaction(function () use ($owner, $address, $data) {
            $user = User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $current = $address ? $user->addresses()->whereKey($address->id)->firstOrFail() : new Address;
            $makeDefault = $data['is_default'] || $current->is_default
                || ! $user->addresses()->where('is_default', true)->exists();
            if ($makeDefault) {
                $user->addresses()->when($current->exists, fn ($query) => $query->where('id', '!=', $current->id))
                    ->update(['is_default' => false]);
            }
            $current->fill($data);
            $current->user_id = $user->id;
            $current->is_default = $makeDefault;
            $current->save();

            return $current;
        });
    }

    public function makeDefault(User $owner, Address $address): void
    {
        DB::transaction(function () use ($owner, $address) {
            $user = User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $current = $user->addresses()->whereKey($address->id)->firstOrFail();
            $user->addresses()->where('id', '!=', $current->id)->update(['is_default' => false]);
            $current->update(['is_default' => true]);
        });
    }

    public function delete(User $owner, Address $address): void
    {
        DB::transaction(function () use ($owner, $address) {
            $user = User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $current = $user->addresses()->whereKey($address->id)->firstOrFail();
            $wasDefault = $current->is_default;
            $current->delete();
            if ($wasDefault) {
                $user->addresses()->update(['is_default' => false]);
                $user->addresses()->orderBy('id')->first()?->update(['is_default' => true]);
            }
        });
    }
}
