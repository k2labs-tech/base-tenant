<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each user has a session open.
 *
 * A table of our own rather than Laravel's `sessions`, for two reasons that
 * both matter. Laravel's table only exists when the installation runs the
 * database session driver, and this has to work whatever the host chose. And
 * revoking a session by deleting its row only works for that same driver --
 * here, revocation is a flag the tracking middleware reads on the next
 * request, which ends the session on any driver.
 *
 * The cost is honest and worth naming: revocation takes effect on the revoked
 * session's next request, not instantly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->index();
            $table->foreignUuid('account_id')->nullable()->index();

            // The framework's session id, hashed. It is a bearer credential:
            // anybody holding it is the session, so storing it in the clear
            // would turn a leaked backup into a set of live logins.
            $table->string('session_id', 64)->unique();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();

            $table->timestamp('last_active_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
