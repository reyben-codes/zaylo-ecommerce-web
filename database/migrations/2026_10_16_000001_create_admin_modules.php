<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin Center modules: complaints and disputes, seller compliance, commission records,
     * announcements and policies (platform settings), and support chat with the admin.
     */
    public function up(): void
    {
        // The deployed database already has disputes and commissions (created before migrations covered them).
        if (! Schema::hasTable('disputes')) {
            Schema::create('disputes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('open');
                $table->text('reason');
                $table->text('resolution')->nullable();
                $table->timestamps();
            });
        }
        Schema::table('disputes', function (Blueprint $table) {
            if (! Schema::hasColumn('disputes', 'category')) {
                $table->string('category', 40)->default('other');
            }
            if (! Schema::hasColumn('disputes', 'evidence')) {
                // JSON list of uploaded file paths on the local disk.
                $table->text('evidence')->nullable();
            }
            if (! Schema::hasColumn('disputes', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable();
            }
        });

        if (! Schema::hasTable('dispute_messages')) {
            Schema::create('dispute_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dispute_id')->index();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->string('attachment_path')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commissions')) {
            Schema::create('commissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->unique();
                $table->unsignedBigInteger('seller_id')->index();
                $table->decimal('rate', 5, 2)->default(10);
                $table->decimal('amount', 12, 2);
                $table->string('status')->default('earned');
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('commissions', 'settled_at')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->timestamp('settled_at')->nullable();
            });
        }

        if (! Schema::hasTable('compliance_warnings')) {
            Schema::create('compliance_warnings', function (Blueprint $table) {
                $table->id();
                // The seller's user account.
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
                $table->string('reason', 40);
                $table->text('details');
                $table->string('action', 20)->default('warning');
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->string('title', 120);
                $table->text('body');
                $table->string('audience', 20)->default('all');
                $table->boolean('is_published')->default(true);
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_policies')) {
            Schema::create('platform_policies', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 60)->unique();
                $table->string('title', 120);
                $table->longText('body');
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_threads')) {
            Schema::create('support_threads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_thread_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['support_thread_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['support_messages', 'support_threads', 'platform_policies', 'announcements', 'compliance_warnings', 'dispute_messages'] as $table) {
            Schema::dropIfExists($table);
        }
        // disputes and commissions predate this migration on the deployed database; only the added columns go.
        foreach (['category', 'evidence', 'resolved_at'] as $column) {
            if (Schema::hasColumn('disputes', $column)) {
                Schema::table('disputes', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
        if (Schema::hasColumn('commissions', 'settled_at')) {
            Schema::table('commissions', fn (Blueprint $table) => $table->dropColumn('settled_at'));
        }
    }
};
