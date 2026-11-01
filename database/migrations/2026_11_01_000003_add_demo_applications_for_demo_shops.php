<?php

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const REGION = ['130000000', 'National Capital Region (NCR)'];

    // Shop name => demo TIN, main category, city code and name, barangay code and name, street, postal code.
    private const SHOPS = [
        'Daily Kitchen Co.' => ['999-000-000-001', 'kitchen_appliances', '137404000', 'Quezon City', '137404001', 'Alicia', 'Demo Unit 1, Sample Street', '1105'],
        'Homestead Living' => ['999-000-000-002', 'furniture_decor', '137602000', 'City of Makati', '137602001', 'Bangkal', 'Demo Unit 2, Sample Street', '1233'],
        'Greenhand Supply' => ['999-000-000-003', 'gardening_tools', '137403000', 'City of Pasig', '137403001', 'Bagong Ilog', 'Demo Unit 3, Sample Street', '1600'],
        'Steadfast Works' => ['999-000-000-004', 'home_improvement_tools', '137402000', 'City of Marikina', '137402001', 'Barangka', 'Demo Unit 4, Sample Street', '1803'],
        'Softnest Linens' => ['999-000-000-005', 'bedding_bath', '137401000', 'City of Mandaluyong', '137401001', 'Addition Hills', 'Demo Unit 5, Sample Street', '1550'],
    ];

    private const OWNERS = [
        'Daily Kitchen Co.' => 'Maria Santos',
        'Homestead Living' => 'Paolo Reyes',
        'Greenhand Supply' => 'Liza Mendoza',
        'Steadfast Works' => 'Carlo Ramos',
        'Softnest Linens' => 'Ana Villanueva',
    ];

    private const DOCUMENTS = [
        'government_id' => 'Government-issued ID',
        'dti_certificate' => 'DTI Certificate',
        'bir_2303' => 'BIR Form 2303',
    ];

    public function up(): void
    {
        // Only the deployed schema keeps shop names in seller_profiles.
        if (! Schema::hasTable('seller_applications') || ! Schema::hasTable('seller_profiles')) {
            return;
        }

        foreach (self::SHOPS as $shop => [$tin, $category, $cityCode, $city, $barangayCode, $barangay, $street, $postalCode]) {
            $ownerId = DB::table('seller_profiles')->where('store_name', $shop)->value('user_id');
            if (! $ownerId) {
                echo "{$shop}: shop not found, skipped".PHP_EOL;
                continue;
            }

            $owner = self::OWNERS[$shop];
            DB::table('users')->where('id', $ownerId)->update(['name' => $owner, 'updated_at' => now()]);
            $address = "{$street}, {$barangay}, {$city}, ".self::REGION[1].", {$postalCode}";
            $paths = [];
            foreach (self::DOCUMENTS as $document => $title) {
                $paths[$document.'_path'] = "seller-applications/{$ownerId}/demo-{$document}.pdf";
                $pdf = Pdf::loadView('admin.demo-seller-document-pdf', [
                    'title' => $title,
                    'shop' => $shop,
                    'details' => $this->details($document, $shop, $owner, $tin, $address),
                ])->setPaper('a4', 'portrait');

                if (! Storage::disk('local')->put($paths[$document.'_path'], $pdf->output())) {
                    throw new RuntimeException("Could not store the mock {$title} for {$shop}.");
                }
            }

            DB::table('seller_applications')->updateOrInsert(['user_id' => $ownerId], $paths + [
                'shop_name' => $shop, 'shop_category' => $category, 'seller_type' => 'sole_proprietorship', 'tin' => $tin,
                'region_code' => self::REGION[0], 'region_name' => self::REGION[1],
                'city_code' => $cityCode, 'city_name' => $city, 'barangay_code' => $barangayCode, 'barangay_name' => $barangay,
                'street_address' => $street, 'postal_code' => $postalCode,
                'status' => 'approved', 'submitted_at' => now(), 'reviewed_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            echo "{$shop}: application and mock documents added".PHP_EOL;
        }
    }

    private function details(string $document, string $shop, string $owner, string $tin, string $address): array
    {
        return match ($document) {
            'government_id' => ['Name' => $owner, 'ID number' => 'DEMO-'.substr($tin, -3), 'Address' => $address],
            'dti_certificate' => ['Business name' => $shop, 'Owner' => $owner, 'Certificate number' => 'DEMO-'.substr($tin, -3), 'Business address' => $address],
            'bir_2303' => ['Taxpayer' => $owner, 'Trade name' => $shop, 'Demo TIN' => $tin, 'Registered address' => $address],
        };
    }

    // The applications are harmless to keep; the shops themselves stay too.
    public function down(): void
    {
    }
};
