<?php

return [
    // General
    'groups' => 'Groups',
    'group' => 'Group',
    'group_items' => 'Group Items',
    'group_item' => 'Group Item',
    'items' => 'Items',
    'item' => 'Item',

    // Actions
    'create' => 'Create',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'restore' => 'Restore',
    'view' => 'View',
    'manage' => 'Manage',
    'manage_items' => 'Manage Items',
    'add_item' => 'Add Item',

    // Form Fields
    'name' => 'Name',
    'slug' => 'Slug',
    'status' => 'Status',
    'type' => 'Type',
    'parent' => 'Parent',
    'ranking' => 'Ranking',
    'color' => 'Color',
    'logic_string' => 'Logic String',
    'note' => 'Note',
    'is_default' => 'Default',
    'created_by' => 'Created By',
    'updated_by' => 'Updated By',
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',

    // Status Labels
    'active' => 'Active',
    'inactive' => 'Inactive',
    'trash' => 'Trash',
    'draft' => 'Draft',
    'published' => 'Published',

    // Messages
    'created_successfully' => 'Group created successfully',
    'updated_successfully' => 'Group updated successfully',
    'deleted_successfully' => 'Group deleted successfully',
    'restored_successfully' => 'Group restored successfully',
    'item_created_successfully' => 'Group item created successfully',
    'item_updated_successfully' => 'Group item updated successfully',
    'item_deleted_successfully' => 'Group item deleted successfully',
    'item_restored_successfully' => 'Group item restored successfully',

    // Validation Messages
    'name_required' => 'Group name is required',
    'name_max' => 'Group name must not exceed 125 characters',
    'slug_unique' => 'This slug is already in use',
    'status_required' => 'Group status is required',
    'status_invalid' => 'Invalid group status selected',

    // Descriptions
    'group_description' => 'Groups help organize and categorize related items',
    'item_description' => 'Items belong to groups and can be organized hierarchically',
    'slug_description' => 'URL-friendly identifier for the group',
    'module_description' => 'Select the module this group belongs to',
    'status_description' => 'Set the visibility and availability status',

    // Empty States
    'no_groups' => 'No groups found',
    'no_items' => 'No items found',
    'create_first_group' => 'Create your first group to get started',
    'create_first_item' => 'Add your first item to this group',

    // Bulk Actions
    'bulk_delete' => 'Delete Selected',
    'bulk_restore' => 'Restore Selected',
    'bulk_force_delete' => 'Delete Permanently',
    'confirm_bulk_delete' => 'Are you sure you want to delete the selected groups?',
    'confirm_bulk_restore' => 'Are you sure you want to restore the selected groups?',
    'confirm_bulk_force_delete' => 'Are you sure you want to permanently delete the selected groups? This action cannot be undone.',

    // Confirmations
    'confirm_delete' => 'Are you sure you want to delete this group?',
    'confirm_restore' => 'Are you sure you want to restore this group?',
    'confirm_force_delete' => 'Are you sure you want to permanently delete this group? This action cannot be undone.',
    'confirm_item_delete' => 'Are you sure you want to delete this item?',
    'confirm_item_restore' => 'Are you sure you want to restore this item?',
    'confirm_item_force_delete' => 'Are you sure you want to permanently delete this item? This action cannot be undone.',

    // Statistics
    'total_groups' => 'Total Groups',
    'active_groups' => 'Active Groups',
    'inactive_groups' => 'Inactive Groups',
    'trashed_groups' => 'Trashed Groups',
    'total_items' => 'Total Items',
    'active_items' => 'Active Items',
    'default_items' => 'Default Items',

    // Modules
    'global' => 'Global',
    'cms' => 'CMS',
    'ecommerce' => 'E-commerce',
    'blog' => 'Blog',
    'user' => 'User Management',
    'settings' => 'Settings',
];
