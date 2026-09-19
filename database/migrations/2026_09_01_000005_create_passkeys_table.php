<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passkeys registered by users.
 *
 * The credential itself is kept as the library's own serialised
 * `CredentialRecord` rather than spread across a dozen columns. Mapping those
 * fields by hand is how a subtle mistake in the trust path or the counter
 * turns into a verification that quietly always passes; letting the library
 * own its own shape means an upgrade of the library upgrades the storage.
 *
 * `credential_id` is duplicated out of it only so the record can be found
 * during an assertion, which is a lookup by id and nothing more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->index();

            // Base64url of the raw credential id. Unique across the
            // installation: an authenticator's credential belongs to one
            // account and finding two would mean something is very wrong.
            $table->string('credential_id', 512)->unique();

            $table->string('name');
            $table->json('record');

            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
