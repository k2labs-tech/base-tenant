<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A link between a user here and an identity at an external provider.
 *
 * `UNIQUE (provider, provider_id)` is the whole safety story: it makes it
 * impossible for one Google account to end up signing in as two different
 * users, whatever a race between two callbacks does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');

            $table->string('provider');

            // The provider's own identifier, never the email: an email can be
            // changed at the provider and reassigned to somebody else, and an
            // identifier cannot.
            $table->string('provider_id');

            $table->string('avatar')->nullable();

            // Encrypted at rest. A refresh token is a standing grant to act as
            // the user at the provider, and a database dump should not be one.
            $table->text('token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
