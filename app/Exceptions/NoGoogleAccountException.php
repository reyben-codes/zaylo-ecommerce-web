<?php

namespace App\Exceptions;

/**
 * A sign-in-only Google login (riders, who must apply with the form first) found no account with this Google
 * identity or email. Nothing is created.
 */
class NoGoogleAccountException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('No account for this Google identity.');
    }
}
