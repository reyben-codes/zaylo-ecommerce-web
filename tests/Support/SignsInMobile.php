<?php

namespace Tests\Support;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;

/**
 * Signs the next API requests in as a mobile app user with a real bearer token, the way the app does.
 */
trait SignsInMobile
{
    protected function signInMobile(User $user): static
    {
        // The token guard keeps the user it resolved, so switching accounts mid-test needs a fresh guard.
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('Test device'));
    }

    /** A rider the rider app lets in: an active account with a profile approved by a logistics partner. */
    protected function approvedRider(array $attributes = []): User
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active'] + $attributes);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Partner '.$user->id, 'slug' => 'partner-'.$user->id, 'status' => 'approved']);
        Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $partner->id, 'vehicle_type' => 'Motorcycle', 'status' => 'approved', 'is_active' => true]);

        return $user;
    }
}
