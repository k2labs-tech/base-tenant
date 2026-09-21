<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usage metering: one running total per account, metric and period, plus the
 * trail of movements that produced it.
 *
 * `period` is an empty string and never null for metrics that do not reset.
 * A unique index treats two NULLs as different rows in MySQL and Postgres, so
 * a nullable column here would quietly allow a second counter for the same
 * account and metric, and every reading would be wrong by whatever landed in
 * the row nobody looked at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('metric');
            $table->string('period')->default('');
            $table->bigInteger('value')->default(0);

            // The last threshold the account was warned about, so a counter
            // sitting at 85% does not send a notification on every increment.
            // Cleared when usage drops back below it.
            $table->unsignedTinyInteger('notified_threshold')->nullable();

            $table->timestamps();

            $table->unique(['account_id', 'metric', 'period']);
            $table->index(['metric', 'period']);
        });

        Schema::create('usage_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('metric');
            $table->string('period')->default('');

            // Signed: a decrement is a negative delta, so the events always
            // sum to the counter and a disagreement between the two is a bug
            // that can be seen rather than guessed at.
            $table->bigInteger('delta');

            $table->nullableUuidMorphs('subject');
            $table->json('metadata')->nullable();

            // Stamped once the delta has been reported to the billing
            // provider. Reporting reads unstamped rows, so a run that dies
            // half way repeats nothing and loses nothing.
            $table->timestamp('reported_at')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['account_id', 'metric', 'created_at']);
            $table->index(['reported_at', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
        Schema::dropIfExists('usage_counters');
    }
};
