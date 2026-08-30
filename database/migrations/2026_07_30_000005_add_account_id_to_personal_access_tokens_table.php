<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Binds an API token to an account so requests authenticated by token resolve
 * their tenant without depending on a session.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        if (Schema::hasColumn('personal_access_tokens', 'account_id')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->uuid('account_id')->nullable()->index();
        });
    }
};
