<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The number separators were `char()` without a length, which is
     * `char(255)`. PostgreSQL pads a CHAR to its full width, so the comma a
     * user picked came back as a comma and 254 spaces and every amount on the
     * screen was formatted with it. MySQL trims CHAR on the way out and SQLite
     * ignores the type, which is why it only showed on one of the three.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        foreach (['decimals_separator', 'thousands_separator'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                continue;
            }

            DB::table('users')->update([$column => DB::raw("rtrim({$column})")]);

            Schema::table('users', function (Blueprint $table) use ($column) {
                $table->string($column, 1)->default($column === 'decimals_separator' ? ',' : '.')->change();
            });
        }
    }

    /**
     * Not reversed: `char(255)` was the defect.
     */
    public function down(): void
    {
        //
    }
};
