<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-account feature overrides, layered on top of the subscription plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('boolean');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'key']);
            $table->index('key');
        });
    }
};
