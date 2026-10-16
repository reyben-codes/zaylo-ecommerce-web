<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The application stores couriers as role `rider` (AuthController, RoleMiddleware,
 * rider sign-up), but databases built from the original schema only allow
 * buyer/seller/courier/admin, so saving a rider fails with "Data truncated".
 * Adds `rider` to the MySQL enum; `courier` is kept for any existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->usesRoleEnum()) {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY role ENUM('buyer','seller','courier','rider','admin') NOT NULL DEFAULT 'buyer'");
    }

    public function down(): void
    {
        if (! $this->usesRoleEnum() || DB::table('users')->where('role', 'rider')->exists()) {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY role ENUM('buyer','seller','courier','admin') NOT NULL DEFAULT 'buyer'");
    }

    private function usesRoleEnum(): bool
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || ! Schema::hasColumn('users', 'role')) {
            return false;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM users WHERE Field = 'role'");

        return $column && str_starts_with(strtolower($column->Type), 'enum(');
    }
};
