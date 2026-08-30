<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-permission tables, adapted to the UUID keys this package uses
 * and to `account_id` as the team column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->string('group')->nullable();
            $table->string('display_name')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
            $table->index('group');
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->uuid('account_id');

            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_index');
            $table->index('account_id', 'model_has_permissions_account_index');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();

            $table->primary(
                ['account_id', 'permission_id', 'model_id', 'model_type'],
                'model_has_permissions_primary'
            );
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->uuid('role_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->uuid('account_id');

            $table->index(['model_id', 'model_type'], 'model_has_roles_model_index');
            $table->index('account_id', 'model_has_roles_account_index');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->primary(
                ['account_id', 'role_id', 'model_id', 'model_type'],
                'model_has_roles_primary'
            );
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->uuid('role_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_primary');
        });
    }
};
