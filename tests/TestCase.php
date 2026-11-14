<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        // Simulated account switches need the same password snapshot that a real web Login event creates.
        if ($this->app['auth']->getDefaultDriver() === 'web') {
            $this->withSession(['password_hash_web' => $this->app['auth']->guard('web')
                ->hashPasswordForCookie((string) $user->getAuthPassword())]);
        }

        return $this;
    }
}
