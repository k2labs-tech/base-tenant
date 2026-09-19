<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom domains a customer points at the product.
 *
 * `accounts.domain` already held one hostname and the resolver already read
 * it, but a single nullable column cannot carry the part that matters: proof
 * that the customer owns the name. Without that, anyone can point a domain at
 * somebody else's account and be served their data.
 *
 * So the hostname moves here, next to its verification state, and
 * `accounts.domain` stays where it is as the legacy fallback for installations
 * that were already resolving through it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('account_id')->index();

            // Unique across the whole installation, not per account: two
            // customers cannot both own the same name, and the database is the
            // only place that can enforce it under concurrency.
            $table->string('hostname')->unique();

            $table->string('verification_token', 64);
            $table->string('status')->default('pending')->index();
            $table->boolean('is_primary')->default(false);

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error')->nullable();

            $table->timestamps();

            // The resolver reads by hostname on every request; the management
            // screen reads by account.
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_domains');
    }
};
