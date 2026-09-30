<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Three nullable columns, none of which changes what 3.0 sends.
     *
     * `body` keeps the exact bytes a delivery sent, so a manual redelivery can
     * send them again rather than an envelope rebuilt later with a new id, a
     * new timestamp or a builder that has changed since. `redelivery_of` says
     * which delivery a redelivery repeats. `degraded_at` marks an endpoint
     * that reached the failure limit under the `degrade` action and is still
     * receiving.
     */
    public function up(): void
    {
        if (Schema::hasTable('outbound_webhook_deliveries') && ! Schema::hasColumn('outbound_webhook_deliveries', 'body')) {
            Schema::table('outbound_webhook_deliveries', function (Blueprint $table): void {
                $table->longText('body')->nullable();
                $table->uuid('redelivery_of')->nullable()->index();
            });
        }

        if (Schema::hasTable('outbound_webhooks') && ! Schema::hasColumn('outbound_webhooks', 'degraded_at')) {
            Schema::table('outbound_webhooks', function (Blueprint $table): void {
                $table->timestamp('degraded_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('outbound_webhook_deliveries', 'body')) {
            Schema::table('outbound_webhook_deliveries', function (Blueprint $table): void {
                $table->dropIndex(['redelivery_of']);
                $table->dropColumn(['body', 'redelivery_of']);
            });
        }

        if (Schema::hasColumn('outbound_webhooks', 'degraded_at')) {
            Schema::table('outbound_webhooks', function (Blueprint $table): void {
                $table->dropColumn('degraded_at');
            });
        }
    }
};
