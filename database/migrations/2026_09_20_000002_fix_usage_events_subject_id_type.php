<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `usage_events.subject` was declared with `nullableMorphs()`, which makes
     * `subject_id` a `bigint`, while every model in the package has a UUID key.
     * SQLite stores the UUID in it anyway; PostgreSQL and MySQL reject it, and
     * with it every metered write — uploading a file, running an import — that
     * records what it measured.
     */
    public function up(): void
    {
        if (! Schema::hasTable('usage_events')) {
            return;
        }

        $column = collect(Schema::getColumns('usage_events'))
            ->firstWhere('name', 'subject_id');

        // Already a UUID column: created that way, or widened by this migration.
        if ($column === null || ! str_contains(strtolower((string) $column['type_name']), 'int')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            // PostgreSQL will not cast bigint to uuid on its own. No row can
            // hold a subject today — the insert that would have written one is
            // exactly what fails — so the cast only has nulls to convert.
            DB::statement('alter table usage_events alter column subject_id type uuid using subject_id::text::uuid');

            return;
        }

        Schema::table('usage_events', function (Blueprint $table) {
            $table->uuid('subject_id')->nullable()->change();
        });
    }

    /**
     * Not reversed: going back to `bigint` would throw away the subject of
     * every metered event.
     */
    public function down(): void
    {
        //
    }
};
