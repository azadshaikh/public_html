<?php

use Illuminate\Contracts\Cache\Factory;
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
        // This migration creates Spatie Laravel Permission tables with Astero customizations:
        // - Added display_name, module_slug, group, audit fields (created_by, updated_by, deleted_by), and soft deletes
        // - Columns defined in correct order for SQLite compatibility
        // - Indexes added on module_slug and group for performance
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        throw_if($teams && empty($columnNames['team_foreign_key'] ?? null), Exception::class, 'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::create($tableNames['permissions'], static function (Blueprint $table): void {
            // Primary key
            $table->bigIncrements('id'); // permission id

            // Permission information
            $table->string('name');       // For MyISAM use string('name', 225); // (or 166 for InnoDB with Redundant/Compact row format)
            $table->string('display_name')->nullable(); // Added for Astero customization
            $table->string('guard_name'); // For MyISAM use string('guard_name', 25);

            // Organization
            $table->string('module_slug')->nullable(); // Added for Astero customization
            $table->string('group')->nullable(); // Added for Astero customization

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable(); // Added for Astero customization
            $table->unsignedBigInteger('updated_by')->nullable(); // Added for Astero customization
            $table->unsignedBigInteger('deleted_by')->nullable(); // Added for Astero customization

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes(); // Added for Astero customization

            // Indexes
            $table->index('module_slug'); // Index for faster queries on module_slug
            $table->index('group'); // Index for faster queries on group
            $table->unique(['name', 'guard_name']);

            // Note: Foreign key constraints for audit fields are not added for permissions
            // as this is a system table that may be seeded before users exist
        });

        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teams, $columnNames): void {
            // Primary key
            $table->bigIncrements('id'); // role id

            // Team support (if enabled)
            if ($teams || config('permission.testing')) { // permission.testing is a fix for sqlite testing
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }

            // Role information
            $table->string('name');       // For MyISAM use string('name', 225); // (or 166 for InnoDB with Redundant/Compact row format)
            $table->string('display_name')->nullable(); // Added for Astero customization
            $table->string('guard_name'); // For MyISAM use string('guard_name', 25);

            // Status
            $table->string('status')->default('active'); // Added for Astero customization

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable(); // Added for Astero customization
            $table->unsignedBigInteger('updated_by')->nullable(); // Added for Astero customization
            $table->unsignedBigInteger('deleted_by')->nullable(); // Added for Astero customization

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes(); // Added for Astero customization

            // Indexes
            $table->index('status'); // Index for faster queries on status

            // Constraints
            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }

            // Note: Foreign key constraints for audit fields are not added for roles
            // as this is a system table that may be seeded before users exist
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams): void {
            $table->unsignedBigInteger($pivotPermission);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->onDelete('cascade');
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');

                $table->primary([$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            }

        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams): void {
            $table->unsignedBigInteger($pivotRole);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->onDelete('cascade');
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');

                $table->primary([$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
            } else {
                $table->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
            }
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        resolve(Factory::class)
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
