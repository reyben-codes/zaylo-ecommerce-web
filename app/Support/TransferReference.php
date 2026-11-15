<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class TransferReference
{
    public const PATTERN = '/\A[A-Z0-9]{13}\z/';

    public static function rules(): array
    {
        return ['required', 'string', 'regex:'.self::PATTERN];
    }

    public static function check(string $reference, string $field = 'reference'): string
    {
        if (! preg_match(self::PATTERN, $reference)) {
            throw ValidationException::withMessages([$field => 'Enter exactly 13 uppercase letters or numbers, e.g. REF20261115AB.']);
        }

        return $reference;
    }
}
