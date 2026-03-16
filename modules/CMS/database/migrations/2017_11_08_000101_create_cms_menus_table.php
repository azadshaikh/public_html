<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Unified cms_menus table that stores both menu containers and menu items
     * using a self-referential parent_id for hierarchy.
     */
    public function up(): void
    {
        Schema::create('cms_menus', function (Blueprint $table): void {
            // Primary Key
            $table->id();

            // Type & Hierarchy
            $table->string('type')->default('container')->comment('Type: container (menu), custom, page, category, post');
            $table->foreignId('parent_id')->nullable()->comment('Parent menu/item for hierarchy')->constrained('cms_menus')->cascadeOnDelete();

            // Basic Information
            $table->string('name')->comment('Display name of the menu/item');
            $table->string('title')->nullable()->comment('Display title for menu items');
            $table->string('slug')->nullable()->comment('URL-friendly identifier (for containers)');
            $table->string('link_title')->nullable()->comment('Alternative title for the link (tooltip)');
            $table->text('description')->nullable()->comment('Optional description');

            // Link Configuration (for items)
            $table->string('url')->nullable()->comment('URL this menu item links to');
            $table->unsignedBigInteger('object_id')->nullable()->comment('ID of related object (page_id if type=page)');
            $table->string('target')->default('_self')->comment('Link target: _self, _blank, etc.');

            // Styling & Presentation
            $table->string('icon')->nullable()->comment('Icon class or identifier');
            $table->string('css_classes')->nullable()->comment('Custom CSS classes for styling');
            $table->string('link_rel')->nullable()->comment('Rel attribute for the link');

            // Ordering & Location
            $table->integer('sort_order')->default(0)->comment('Sort order within parent');
            $table->string('location')->default('')->comment('Menu location: primary, footer, sidebar (for containers)');

            // Status & Visibility
            $table->boolean('is_active')->default(true)->comment('Whether active and should be displayed');

            // Flexible Storage
            $table->json('metadata')->nullable()->comment('Extra configuration for rendering behaviour');

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes for Performance
            $table->unique(['slug', 'deleted_at'], 'cms_menus_slug_unique');
            $table->unique(['location', 'deleted_at'], 'cms_menus_location_unique');
            $table->index(['parent_id', 'sort_order'], 'cms_menus_hierarchy_index');
            $table->index(['type', 'object_id'], 'cms_menus_type_object_index');
            $table->index(['type', 'is_active'], 'cms_menus_type_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_menus');
    }
};
