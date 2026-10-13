<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desktop rider applications: license expiry and the driver's license and OR/CR documents
     * the logistics partner reviews.
     */
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->date('license_expires_on')->nullable()->after('license_number');
            $table->string('license_document_path')->nullable()->after('license_expires_on');
            $table->string('or_cr_document_path')->nullable()->after('license_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['license_expires_on', 'license_document_path', 'or_cr_document_path']);
        });
    }
};
