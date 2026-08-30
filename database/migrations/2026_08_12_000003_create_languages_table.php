<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The languages the application offers, as data rather than as a config array.
 *
 * The point is not any particular language: it is being able to turn one on or
 * off without a deploy. A locale list that lives in a file needs a release to
 * change, and a release is exactly what nobody wants to do to add Catalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');

            // What the language calls itself. A picker that lists «Spanish» to
            // a Spanish speaker is a picker written for the developer.
            $table->string('native_name');

            $table->boolean('enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['enabled', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
