<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Help & support messages can carry a photo (stored privately; see App\Services\ChatPhotos). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_messages') && ! Schema::hasColumn('support_messages', 'image_path')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->string('image_path')->nullable()->after('body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('support_messages') && Schema::hasColumn('support_messages', 'image_path')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }
};
