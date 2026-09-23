<?php

namespace Database\Seeders;

use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        Voucher::firstOrCreate(['code' => 'ZAYLOSHIP'], ['type' => 'free_shipping']);
    }
}
