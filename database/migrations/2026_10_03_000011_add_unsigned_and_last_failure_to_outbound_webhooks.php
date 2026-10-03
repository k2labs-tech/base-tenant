<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two changes to `outbound_webhooks`, neither of which alters what 3.1 sends.
 *
 * `secret` becomes nullable so an endpoint can be registered without one when
 * `webhooks.allow_unsigned` is on; with it off, an endpoint without a secret
 * is refused at delivery, as before. `last_failed_at` records the last failed
 * attempt, which is what `webhooks.degraded_cooldown` counts from.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outbound_webhooks')) {
            return;
        }

        Schema::table('outbound_webhooks', function (Blueprint $table): void {
            $table->text('secret')->nullable()->change();
        });

        if (! Schema::hasColumn('outbound_webhooks', 'last_failed_at')) {
            Schema::table('outbound_webhooks', function (Blueprint $table): void {
                $table->timestamp('last_failed_at')->nullable();
            });
        }
    }

    /**
     * Endpoints registered without a secret have to be removed, or given one,
     * before rolling back: the column cannot go back to NOT NULL over them.
     */
    public function down(): void
    {
        if (Schema::hasColumn('outbound_webhooks', 'last_failed_at')) {
            Schema::table('outbound_webhooks', function (Blueprint $table): void {
                $table->dropColumn('last_failed_at');
            });
        }

        Schema::table('outbound_webhooks', function (Blueprint $table): void {
            $table->text('secret')->nullable(false)->change();
        });
    }
};
