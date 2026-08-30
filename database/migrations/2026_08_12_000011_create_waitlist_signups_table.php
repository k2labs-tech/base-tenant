<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People who asked to be told when the product opens.
 *
 * Not scoped to an account: they do not have one yet, and that is the point.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_signups', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('email')->unique();
            $table->string('name')->nullable();

            // Where they came from, so a launch can tell which channel worked.
            $table->string('source')->nullable();
            $table->string('referrer')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamp('invited_at')->nullable();
            $table->timestamps();

            $table->index('invited_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_signups');
    }
};
