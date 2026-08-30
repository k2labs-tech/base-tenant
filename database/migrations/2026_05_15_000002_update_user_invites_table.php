<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the account, role, inviter and acceptance columns to invitations.
 *
 * `accepted_at` deliberately does not use `->after('expires_at')`: that column
 * is added by a later migration, and MySQL rejects an AFTER clause naming a
 * column that does not exist yet. SQLite ignores AFTER entirely, which is why
 * this only ever failed on a real database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_invites', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_invites', 'account_id')) {
                $table->uuid('account_id')->nullable()->after('token');
            }

            if (! Schema::hasColumn('user_invites', 'role_id')) {
                $table->uuid('role_id')->nullable()->after('account_id');
            }

            if (! Schema::hasColumn('user_invites', 'invited_by')) {
                $table->uuid('invited_by')->nullable()->after('role_id');
            }

            if (! Schema::hasColumn('user_invites', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('invited_by');
            }
        });

        Schema::table('user_invites', function (Blueprint $table): void {
            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
        });
    }
};
