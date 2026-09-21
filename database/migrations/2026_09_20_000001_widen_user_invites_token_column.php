<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invitation tokens are 64 characters (`Str::random(64)`), but the column
     * was created as `varchar(50)`. SQLite ignores the length and hides the
     * mismatch; MySQL truncates or rejects the value and PostgreSQL rejects it,
     * which breaks the whole invitation flow.
     */
    public function up(): void
    {
        if (! Schema::hasTable('user_invites')) {
            return;
        }

        Schema::table('user_invites', function (Blueprint $table) {
            $table->string('token', 64)->change();
        });
    }

    /**
     * Not reversed: narrowing the column back to 50 would truncate the tokens
     * of every pending invitation.
     */
    public function down(): void
    {
        //
    }
};
