<?php

namespace App\Support;

/**
 * The account types a person can sign up for, as shown on the sign-up chooser (auth.panels.register-choice) and on
 * the "Signing up as" chip of each form. Keys are the role values the sign-up form posts ("courier" is a rider).
 * steps counts the form's wizard steps plus the email code.
 */
final class SignUpRoles
{
    public static function all(): array
    {
        return [
            'buyer' => [
                'name' => 'Buyer', 'icon' => 'fa-bag-shopping', 'steps' => 3, 'approval' => null, 'needs' => null,
                'summary' => 'Shop from local sellers, save favorites and track your orders.',
            ],
            'seller' => [
                'name' => 'Seller', 'icon' => 'fa-store', 'steps' => 6, 'approval' => 'Admin approval', 'needs' => 'ID, TIN and business documents',
                'summary' => 'Open a shop and sell your products to buyers across the Philippines.',
            ],
            'courier' => [
                'name' => 'Rider', 'icon' => 'fa-motorcycle', 'steps' => 7, 'approval' => 'Partner approval', 'needs' => "Driver's license and OR/CR",
                'summary' => "Deliver parcels for one of ZAYLO's logistics partners, using the ZAYLO app.",
            ],
            'logistics' => [
                'name' => 'Logistics partner', 'icon' => 'fa-truck-fast', 'steps' => 7, 'approval' => 'Admin approval', 'needs' => 'SEC, BIR and business permit',
                'summary' => 'Register your delivery company and manage your own riders.',
            ],
        ];
    }
}
