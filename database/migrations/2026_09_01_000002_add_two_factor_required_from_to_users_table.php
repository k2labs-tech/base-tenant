<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the two-factor requirement started applying to this user.
 *
 * The grace period has to be counted from somewhere, and neither candidate
 * works alone: `created_at` gives somebody who has used the product for a year
 * a deadline already in the past, and "now, every time" means the deadline
 * never arrives. So the moment is stamped once -- when the rule is switched on,
 * or when the user joins an account that already has it on -- and the grace
 * period runs from there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'two_factor_required_from')) {
                $table->timestamp('two_factor_required_from')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'two_factor_required_from')) {
                $table->dropColumn('two_factor_required_from');
            }
        });
    }
};
