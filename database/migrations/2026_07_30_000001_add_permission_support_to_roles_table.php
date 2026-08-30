<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the existing roles table with spatie/laravel-permission.
 *
 * The package identifies a role by `name`, while this table used `key` as the
 * identifier and `name` as the human label. The columns are re-arranged so
 * `name` holds the identifier, `display_name` holds the label, and `key` is
 * kept as a synced alias for host applications still querying it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('roles', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }

            if (! Schema::hasColumn('roles', 'guard_name')) {
                $table->string('guard_name')->default('web')->after('display_name');
            }

            if (! Schema::hasColumn('roles', 'account_id')) {
                $table->uuid('account_id')->nullable()->after('id');
                $table->index('account_id', 'roles_account_id_index');
            }

            if (! Schema::hasColumn('roles', 'is_system')) {
                $table->boolean('is_system')->default(false);
            }
        });

        $grammar = DB::getQueryGrammar();

        DB::table('roles')->whereNull('display_name')->update([
            'display_name' => DB::raw($grammar->wrap('name')),
        ]);

        DB::table('roles')->update([
            'name' => DB::raw($grammar->wrap('key')),
        ]);

        Schema::table('roles', function (Blueprint $table): void {
            $table->unique(['account_id', 'name', 'guard_name'], 'roles_account_name_guard_unique');
        });
    }
};
