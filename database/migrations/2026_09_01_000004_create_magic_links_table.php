<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-use sign-in links.
 *
 * The token is stored hashed for the same reason a password is: whoever holds
 * the raw value can become the user, so a leaked backup of this table must not
 * be a set of working logins. Only the email in the link is a plain value, and
 * it identifies nothing on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_links', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->index();
            $table->foreignUuid('user_id')->nullable()->index();

            $table->string('token', 64)->unique();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_links');
    }
};
