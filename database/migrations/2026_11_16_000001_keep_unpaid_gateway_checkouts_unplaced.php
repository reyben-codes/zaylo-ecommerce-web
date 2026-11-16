<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('escrow_payments')) return;
        $drafts = DB::table('orders')->where('status', 'placed')->where('payment_method', 'gcash')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('escrow_payments')
                ->whereColumn('escrow_payments.order_id', 'orders.id')->where('escrow_payments.status', 'pending'))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('payments')
                ->whereColumn('payments.order_id', 'orders.id')->where('payments.status', 'pending')->whereNull('payments.paid_at'));
        if (! Schema::hasColumn('orders', 'user_id')) {
            $drafts->whereNotExists(fn ($query) => $query->selectRaw('1')->from('seller_orders')
                ->whereColumn('seller_orders.order_id', 'orders.id')->where('seller_orders.status', '!=', 'pending'));
        }
        $drafts->update(['status' => 'awaiting_payment', 'placed_at' => null]);
    }

    public function down(): void
    {
        // A rollback must not turn an unpaid checkout into a placed order.
    }
};
