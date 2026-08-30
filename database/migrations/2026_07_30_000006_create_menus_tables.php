<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Navigation defined in the database.
 *
 * Product menus are declared in code and synced here; rows owned by an account
 * override ordering, labels and visibility for that tenant only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->nullable()->index();
            $table->string('key');
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'key']);
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            /*
             * The primary key is declared with `$table->primary()` and not with
             * the fluent `->primary()` modifier because of the self-referencing
             * foreign key below.
             *
             * Blueprint::addFluentIndexes() APPENDS the fluent index commands
             * after every command the closure already registered, so a fluent
             * `->primary()` is emitted as an `ALTER TABLE … ADD PRIMARY KEY`
             * that runs AFTER `ALTER TABLE … ADD CONSTRAINT
             * menu_items_parent_id_foreign … REFERENCES menu_items (id)`.
             * PostgreSQL refuses that with "there is no unique constraint
             * matching given keys for referenced table", which made
             * `migrate:fresh` fail on every fresh database. Declaring it here,
             * before the foreigns, puts the two statements in the only order
             * that works.
             */
            $table->uuid('id');
            $table->primary('id');
            $table->uuid('menu_id');
            $table->uuid('parent_id')->nullable();
            $table->uuid('account_id')->nullable()->index();

            $table->string('key');
            $table->string('label')->nullable();
            $table->string('icon')->nullable();
            $table->string('route')->nullable();
            $table->json('route_params')->nullable();
            $table->string('url')->nullable();
            $table->string('target')->nullable();

            $table->string('permission')->nullable();
            $table->string('feature')->nullable();
            $table->string('badge')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('menu_id')->references('id')->on('menus')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('menu_items')->cascadeOnDelete();

            $table->unique(['menu_id', 'account_id', 'key']);
            $table->index(['menu_id', 'parent_id', 'position']);
        });
    }
};
