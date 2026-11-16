<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logistics_providers')) {
            return;
        }

        // Tag primary Zaylo provider with brand and main sorting hub address if blank
        $zaylo = DB::table('logistics_providers')
            ->where(function ($query) {
                $query->where('network_brand', 'zaylo_express')
                    ->orWhere('slug', 'zaylo-express')
                    ->orWhere('name', 'like', '%Express%');
            })
            ->whereNull('parent_id')
            ->orderBy('id')
            ->first();

        if ($zaylo) {
            $updates = ['network_brand' => 'zaylo_express'];
            if (empty($zaylo->street_address)) {
                $updates['street_address'] = 'Zaylo Central Hub, Km 18 West Service Road';
                $updates['region_code'] = '130000000';
                $updates['region_name'] = 'National Capital Region (NCR)';
                $updates['city_code'] = '137404000';
                $updates['city_name'] = 'Quezon City';
                $updates['barangay_code'] = '137404001';
                $updates['barangay_name'] = 'Alicia';
                $updates['postal_code'] = '1105';
            }
            DB::table('logistics_providers')->where('id', $zaylo->id)->update($updates);
        }

        // Tag primary J&T provider with brand if blank
        DB::table('logistics_providers')
            ->where(function ($query) {
                $query->where('network_brand', 'jnt_express')
                    ->orWhere('slug', 'like', '%jt%')
                    ->orWhere('slug', 'like', '%jnt%')
                    ->orWhere('name', 'like', '%J&T%');
            })
            ->whereNull('parent_id')
            ->update(['network_brand' => 'jnt_express']);

        // Tag primary Flash provider with brand if blank
        DB::table('logistics_providers')
            ->where(function ($query) {
                $query->where('network_brand', 'flash_express')
                    ->orWhere('slug', 'like', '%flash%')
                    ->orWhere('name', 'like', '%Flash%');
            })
            ->whereNull('parent_id')
            ->update(['network_brand' => 'flash_express']);
    }

    public function down(): void
    {
        // Safe no-op on down
    }
};

