<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'courier_choice')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('courier_choice', 50)->nullable()->after('payment_method');
            });
        }

        if (Schema::hasTable('disputes')) {
            Schema::table('disputes', function (Blueprint $table) {
                if (! Schema::hasColumn('disputes', 'return_type')) {
                    $table->string('return_type', 50)->nullable()->after('category');
                }
                if (! Schema::hasColumn('disputes', 'return_method')) {
                    $table->string('return_method', 50)->nullable()->after('return_type');
                }
                if (! Schema::hasColumn('disputes', 'return_courier')) {
                    $table->string('return_courier', 50)->nullable()->after('return_method');
                }
                if (! Schema::hasColumn('disputes', 'pickup_date')) {
                    $table->date('pickup_date')->nullable()->after('return_courier');
                }
                if (! Schema::hasColumn('disputes', 'pickup_slot')) {
                    $table->string('pickup_slot', 50)->nullable()->after('pickup_date');
                }
                if (! Schema::hasColumn('disputes', 'dropoff_location')) {
                    $table->string('dropoff_location')->nullable()->after('pickup_slot');
                }
                if (! Schema::hasColumn('disputes', 'dropoff_deadline')) {
                    $table->date('dropoff_deadline')->nullable()->after('dropoff_location');
                }
                if (! Schema::hasColumn('disputes', 'return_tracking_number')) {
                    $table->string('return_tracking_number', 100)->nullable()->after('dropoff_deadline')->index();
                }
                if (! Schema::hasColumn('disputes', 'return_status')) {
                    $table->string('return_status', 50)->nullable()->after('return_tracking_number');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'courier_choice')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('courier_choice');
            });
        }

        if (Schema::hasTable('disputes')) {
            Schema::table('disputes', function (Blueprint $table) {
                $columns = [
                    'return_type', 'return_method', 'return_courier', 'pickup_date',
                    'pickup_slot', 'dropoff_location', 'dropoff_deadline',
                    'return_tracking_number', 'return_status',
                ];
                $existing = array_filter($columns, fn ($col) => Schema::hasColumn('disputes', $col));
                if (! empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }
    }
};
