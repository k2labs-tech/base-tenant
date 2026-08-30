<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 5)->default('en')->after('email_verified_at');
            }
            if (! Schema::hasColumn('users', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('locale');
            }
            if (! Schema::hasColumn('users', 'decimal_places')) {
                $table->unsignedTinyInteger('decimal_places')->default(2)->after('currency');
            }
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 50)->default('UTC')->after('decimal_places');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'currency', 'decimal_places', 'timezone']);
        });
    }
};
