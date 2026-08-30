<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correlative numbering per account.
 *
 * `account_id` is a string and not a uuid column, holding either an account id
 * or the literal `global`. A nullable uuid would have been the obvious shape,
 * but MySQL and Postgres treat two NULLs as distinct in a unique index: two
 * global sequences with the same key would both be allowed, and the numbering
 * would silently fork in two. The same reasoning applies to `period`, which is
 * an empty string when the sequence does not reset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('account_id')->default('global');
            $table->string('key');

            // '2026' for a yearly reset, '2026-08' for monthly, '' for never.
            $table->string('period')->default('');

            // The number the next call will hand out, not the last one given.
            // Reading a counter should not require knowing which convention it
            // follows.
            $table->unsignedBigInteger('next_value')->default(1);

            $table->string('format')->nullable();

            $table->timestamps();

            $table->unique(['account_id', 'key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
