<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || mb_strlen($value) < 8
            || preg_match('/\p{Lu}/u', $value) !== 1
            || preg_match('/\p{Ll}/u', $value) !== 1
            || preg_match('/[\p{P}\p{S}]/u', $value) !== 1) {
            $fail('The :attribute must have at least 8 characters, one uppercase letter, one lowercase letter, and one symbol.');
        }
    }
}
