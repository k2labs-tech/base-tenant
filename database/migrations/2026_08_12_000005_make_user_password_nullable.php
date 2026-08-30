<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user who signs in with Google has no password, and should not be given a
 * random one.
 *
 * A random password is a credential nobody knows, that shows up in every
 * "reset your password" flow as if it meant something. An empty column makes
 * "this person signs in with a provider" a fact the code can read -- and the
 * hasher already refuses an empty hash, so password login simply does not
 * work for them rather than working by accident.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reversible without inventing passwords for the accounts that
        // never had one, so the column is left as it is.
    }
};
