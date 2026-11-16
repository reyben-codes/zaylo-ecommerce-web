<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Must run before the Nov 16 backfill: older deployments require placed_at.
        $column = collect(Schema::getColumns('orders'))->firstWhere('name', 'placed_at');
        if (! $column || $column['nullable']) return;

        // Keep DATETIME/TIMESTAMP semantics and fractional precision of existing records.
        $type = match (strtolower($column['type_name'])) {
            'timestamp' => 'timestamp',
            'datetime' => 'dateTime',
            default => throw new RuntimeException('Unexpected orders.placed_at column type.'),
        };
        $precision = preg_match('/\((\d+)\)/', $column['type'], $matches) ? (int) $matches[1] : null;
        Schema::table('orders', function (Blueprint $table) use ($type, $precision) {
            $table->{$type}('placed_at', $precision)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Keep nullable: unpaid checkouts can legitimately have no placement timestamp.
    }
};
