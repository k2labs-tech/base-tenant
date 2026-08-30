<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses this application must stop writing to.
 *
 * Not scoped to an account: a hard bounce is a fact about the address, not
 * about the customer who happened to trigger it, and a second account mailing
 * the same dead mailbox damages the same sending reputation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_suppressions', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Stored lower-cased, and unique, so the guard cannot be walked
            // past by a difference in capitals.
            $table->string('email')->unique();

            $table->string('reason');   // bounce | complaint | manual | unsubscribe
            $table->string('source');   // mailgun | ses | import | ui

            $table->json('metadata')->nullable();
            $table->timestamp('suppressed_at');

            $table->timestamps();

            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
