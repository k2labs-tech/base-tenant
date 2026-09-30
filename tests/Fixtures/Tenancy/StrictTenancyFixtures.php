<?php

declare(strict_types=1);

namespace Base\Tenant\Tests\Fixtures\Tenancy;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables for the tenancy fixture models. Created inside the test's
 * transaction, so they disappear with it.
 */
class StrictTenancyFixtures
{
    public static function createTables(): void
    {
        Schema::create('tenancy_widgets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('account_id')->nullable();
            $table->string('code');
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['account_id', 'code']);
        });

        Schema::create('tenancy_gadgets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('account_id')->nullable();
            $table->unsignedBigInteger('widget_id');
            $table->string('name');
        });

        Schema::create('tenancy_tags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('account_id')->nullable();
            $table->string('name');
        });

        Schema::create('tenancy_tag_widget', function (Blueprint $table): void {
            $table->uuid('account_id')->nullable();
            $table->unsignedBigInteger('widget_id');
            $table->unsignedBigInteger('tag_id');
        });
    }
}
