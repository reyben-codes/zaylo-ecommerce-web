<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How an announcement reaches its audience. Existing ones keep showing in the announcement section only.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('show_in_section')->default(true)->after('audience');
            $table->boolean('show_as_modal')->default(false)->after('show_in_section');
            $table->boolean('send_email')->default(false)->after('show_as_modal');
            $table->dateTime('emailed_at')->nullable()->after('send_email');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['show_in_section', 'show_as_modal', 'send_email', 'emailed_at']);
        });
    }
};
