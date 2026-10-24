<?php

namespace App\Http\Requests;

/**
 * The rider sign-up rules without the uploads or terms. The app calls this first, so a taken email, mobile number,
 * license or plate is reported before the rider waits for two documents to upload.
 */
class MobileRiderRegistrationCheckRequest extends MobileRiderRegistrationRequest
{
    protected bool $withDocuments = false;
}
