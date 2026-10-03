<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per attempt at a delivery.
 *
 * The delivery row keeps only the last response, which is the one thing
 * nobody asks about: whoever is looking at why a receiver never got an event
 * needs to see that the five attempts all timed out, not only the fifth.
 *
 * Integer keys rather than UUIDs: this is the busiest webhook table, written
 * once per attempt and read by delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('outbound_webhook_attempts')) {
            return;
        }

        Schema::create('outbound_webhook_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('account_id');
            $table->uuid('delivery_id');
            $table->uuid('outbound_webhook_id');

            $table->unsignedSmallInteger('attempt');

            // delivered, failed, or postponed (held back by the degraded
            // cooldown without spending an attempt).
            $table->string('outcome', 20);

            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('attempted_at');

            $table->index(['delivery_id', 'attempt']);
            $table->index(['account_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_webhook_attempts');
    }
};
