<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an account be resolved from the request host and carry a lifecycle
 * status independent of its Stripe subscription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounts', 'domain')) {
                $table->string('domain')->nullable()->unique();
            }

            if (! Schema::hasColumn('accounts', 'subdomain')) {
                $table->string('subdomain')->nullable()->unique();
            }

            if (! Schema::hasColumn('accounts', 'status')) {
                $table->string('status')->default('active')->index();
            }
        });
    }
};
