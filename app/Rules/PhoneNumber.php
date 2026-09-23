<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^09[0-9]{9}$/D', $value) !== 1) {
            $fail('The :attribute must be an 11-digit Philippine mobile number starting with 09.');
        }
    }
}
