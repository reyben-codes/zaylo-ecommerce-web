<?php

namespace App\Exceptions;

/**
 * A Google sign-in started on one account type's page matched an existing account of another type
 * (e.g. a seller using the shop sign-in). The account is left untouched.
 */
class WrongAccountTypeException extends \DomainException
{
    public function __construct(public readonly string $accountRole)
    {
        parent::__construct('Google sign-in started for a different account type.');
    }
}
