<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->timestamp('return_approved_at')->nullable();
            $table->foreignId('return_approved_by')->nullable()->constrained('users')->nullOnDelete();
        });
        // Uncollected returns also need confirmation; keep parcels already in transit moving.
        DB::table('disputes')->whereNotNull('return_tracking_number')
            ->whereIn('status', ['open', 'in_review'])
            ->where(fn ($query) => $query->whereNull('return_status')->orWhere('return_status', 'scheduled'))
            ->update(['return_status' => 'pending_seller', 'dropoff_deadline' => null]);
    }

    public function down(): void
    {
        DB::table('disputes')->where('return_status', 'pending_seller')->update(['return_status' => 'scheduled']);
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_approved_by');
            $table->dropColumn('return_approved_at');
        });
    }
};
