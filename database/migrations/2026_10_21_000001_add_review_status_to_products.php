<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New and edited seller listings wait for an admin before buyers can see them (see Product::visibleToBuyers).
        // Products already on sale default to approved, so nothing disappears from the shop.
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'review_status')) {
                $table->string('review_status', 20)->default('approved')->after('is_active');
                $table->index('review_status');
            }
            if (! Schema::hasColumn('products', 'review_note')) {
                $table->text('review_note')->nullable()->after('review_status');
            }
            if (! Schema::hasColumn('products', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('review_note');
            }
            if (! Schema::hasColumn('products', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['review_status']);
            $table->dropColumn(['review_status', 'review_note', 'reviewed_at']);
        });
    }
};
