<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-account credentials for external services, and the webhooks this
 * application sends out.
 *
 * Two tables for deliveries rather than one: a webhook is a standing
 * subscription and a delivery is one attempt at one event. Keeping attempts on
 * the subscription row would mean the endpoint's URL changed underneath the
 * history of what was sent to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');

            $table->string('provider');

            // An account may hold two of the same provider -- two Wubook
            // properties, two mailboxes -- so the label is part of the
            // identity rather than decoration.
            $table->string('label')->default('default');

            // Encrypted at rest. These are the customer's credentials at a
            // third party: a database dump must not be a set of live logins.
            $table->text('credentials')->nullable();

            $table->json('metadata')->nullable();

            $table->boolean('enabled')->default(true);

            // The last health check, so a broken credential is visible without
            // calling the provider to find out.
            $table->string('status')->default('unknown');
            $table->text('status_message')->nullable();
            $table->timestamp('checked_at')->nullable();

            $table->timestamps();

            $table->unique(['account_id', 'provider', 'label']);
            $table->index(['account_id', 'enabled']);
        });

        Schema::create('outbound_webhooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');

            $table->string('name')->nullable();
            $table->string('url');

            // Which events this endpoint wants. `*` takes everything.
            $table->json('events');

            // Used to sign every delivery, so the receiver can tell our
            // requests from anyone else's.
            $table->text('secret');

            $table->boolean('enabled')->default(true);

            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamp('disabled_at')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'enabled']);
        });

        Schema::create('outbound_webhook_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('outbound_webhook_id');

            $table->string('event');
            $table->json('payload');

            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('attempt')->default(0);

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'event', 'created_at']);
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_webhook_deliveries');
        Schema::dropIfExists('outbound_webhooks');
        Schema::dropIfExists('account_connections');
    }
};
