<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every chat can carry a photo or a video with a message: buyer–seller messages, Help & support, logistics chat
 * and the rider–buyer delivery chat. Support messages already had photos.
 */
return new class extends Migration
{
    private const TABLES = ['messages', 'support_messages', 'logistics_messages', 'delivery_messages'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'image_path')) {
                    $table->string('image_path')->nullable();
                }
                if (! Schema::hasColumn($name, 'video_path')) {
                    $table->string('video_path')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) use ($name) {
                // Support photos came with an earlier migration, which removes them itself.
                $columns = array_filter(
                    $name === 'support_messages' ? ['video_path'] : ['image_path', 'video_path'],
                    fn (string $column) => Schema::hasColumn($name, $column),
                );
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
