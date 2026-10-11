<?php

namespace App\Http\Requests;

/**
 * Same rules as RegisterRiderRequest minus the uploads. The app calls this
 * first so a taken email, mobile number, license, or plate is reported before
 * the rider waits for two documents to upload.
 */
class CheckRiderRegistrationRequest extends RegisterRiderRequest
{
    public function rules(): array
    {
        return $this->detailRules();
    }
}
