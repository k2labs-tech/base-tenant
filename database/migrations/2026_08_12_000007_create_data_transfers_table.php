<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One import or export, and how it went.
 *
 * The row exists from the moment the user asks, not from the moment the job
 * picks it up: an import that is queued behind an hour of work should show as
 * pending rather than as nothing at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');

            $table->string('type');           // import | export
            $table->string('handler');        // the Import or Export class
            $table->string('status')->default('pending');

            $table->string('name')->nullable();

            // The uploaded source for an import, the produced file for an
            // export. Both are rows in `files`, so they are metered, purged
            // and downloaded through the same path as everything else.
            $table->uuid('file_id')->nullable();

            // Rows that failed, as a CSV the user can correct and re-upload.
            $table->uuid('error_file_id')->nullable();

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);

            // Which column of the file feeds which field.
            $table->json('mapping')->nullable();
            $table->json('options')->nullable();

            $table->text('message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->uuid('created_by')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_transfers');
    }
};
