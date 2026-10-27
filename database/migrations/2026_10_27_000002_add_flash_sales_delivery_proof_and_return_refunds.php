<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'products' => ['sale_is_flash', 'flash_quantity', 'flash_sold'],
        'shipments' => ['delivery_proof_path', 'handover_confirmed_at'],
        'disputes' => ['return_received_at', 'refund_amount', 'refunded_at'],
    ];

    public function up(): void
    {
        // Flash sales: a short sale on a limited number of units, counted at checkout.
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'sale_is_flash')) {
                    $table->boolean('sale_is_flash')->default(false);
                }
                if (! Schema::hasColumn('products', 'flash_quantity')) {
                    $table->unsignedInteger('flash_quantity')->nullable();
                }
                if (! Schema::hasColumn('products', 'flash_sold')) {
                    $table->unsignedInteger('flash_sold')->default(0);
                }
            });
        }

        // The rider's photo at the door, and the seller's confirmation that the partner collected the parcel.
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('shipments', 'delivery_proof_path')) {
                    $table->string('delivery_proof_path')->nullable();
                }
                if (! Schema::hasColumn('shipments', 'handover_confirmed_at')) {
                    $table->timestamp('handover_confirmed_at')->nullable();
                }
            });
        }

        // The seller receives a returned parcel, then records the refund.
        if (Schema::hasTable('disputes')) {
            Schema::table('disputes', function (Blueprint $table) {
                if (! Schema::hasColumn('disputes', 'return_received_at')) {
                    $table->timestamp('return_received_at')->nullable();
                }
                if (! Schema::hasColumn('disputes', 'refund_amount')) {
                    $table->decimal('refund_amount', 10, 2)->nullable();
                }
                if (! Schema::hasColumn('disputes', 'refunded_at')) {
                    $table->timestamp('refunded_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
            if ($existing) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($existing));
            }
        }
    }
};
