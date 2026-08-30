<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files, owned by an account and optionally attached to one of its records.
 *
 * The morph is nullable on purpose: a media library holds files that belong to
 * the account and to nothing else in particular, and forcing an owner would
 * mean inventing a placeholder record for every one of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');

            $table->nullableMorphs('fileable');

            // Named groups within one owner -- `avatar`, `documents`,
            // `gallery` -- so a model can hold several sets of files without
            // each set needing its own relation.
            $table->string('collection')->default('default');

            $table->string('disk');
            $table->string('path');

            // The name the user recognises, kept apart from the path so that
            // renaming a file never moves a byte.
            $table->string('name');
            $table->string('extension')->nullable();

            // Read from the bytes, not from what the browser claimed.
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('checksum')->nullable();

            // Derived renditions, keyed by name: {"thumb": "path/thumb.jpg"}.
            $table->json('variants')->nullable();
            $table->json('custom_properties')->nullable();

            $table->unsignedInteger('order_column')->default(0);
            $table->uuid('uploaded_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'collection']);
            $table->index(['fileable_type', 'fileable_id', 'collection'], 'files_fileable_collection_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
