<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_invites', function (Blueprint $table) {
            if (! Schema::hasColumn('user_invites', 'account_id')) {
                $table->foreignUuid('account_id')
                    ->nullable()
                    ->after('email')
                    ->constrained('accounts')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('user_invites', 'role')) {
                $table->string('role')->nullable()->after('account_id');
            }

            if (! Schema::hasColumn('user_invites', 'used_at')) {
                $table->timestamp('used_at')->nullable()->after('token');
            }
        });

        // Drop unique on email — same email can be invited to multiple accounts
        try {
            Schema::table('user_invites', function (Blueprint $table) {
                $table->dropUnique(['email']);
            });
        } catch (\Exception $e) {
            // Constraint doesn't exist, skip
        }
    }

    public function down(): void
    {
        Schema::table('user_invites', function (Blueprint $table) {
            if (Schema::hasColumn('user_invites', 'used_at')) {
                $table->dropColumn('used_at');
            }
            if (Schema::hasColumn('user_invites', 'role')) {
                $table->dropColumn('role');
            }
            if (Schema::hasColumn('user_invites', 'account_id')) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            }
        });
    }
};
