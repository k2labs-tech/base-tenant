<?php

return [
    // Management
    'management_title' => 'Account Management',
    'add_new' => 'Add New Account',
    'search_placeholder' => 'Search accounts...',

    // Table headers
    'name' => 'Account Name',
    'users_count' => 'Users',
    'status' => 'Status',
    'actions' => 'Actions',

    // Status
    'active' => 'Active',
    'inactive' => 'Inactive',
    'users' => 'users',

    // Actions
    'edit' => 'Edit',
    'delete' => 'Delete',
    'cancel' => 'Cancel',

    // Messages
    'no_accounts_found' => 'No accounts found.',
    'account_created' => 'Account Created',
    'created_successfully' => 'The account has been created successfully.',
    'account_updated' => 'Account Updated',
    'updated_successfully' => 'The account has been updated successfully.',
    'account_removed' => 'Account Removed',
    'removed_successfully' => 'The account has been removed successfully.',
    'error_deleting_account' => 'Error Deleting Account',
    'cannot_delete_with_users' => 'Cannot delete an account that has users. Please remove all users first.',
    'cannot_delete_with_projects' => 'Cannot delete an account that has projects. Please remove all projects first.',
    'cannot_delete_with_translations' => 'Cannot delete an account that has translations. Please remove all translations first.',
    'cannot_delete_with_relations' => 'Cannot delete an account that has related data in the following tables: :tables',

    // Delete confirmation
    'delete_account' => 'Delete Account',
    'delete_confirmation' => 'Are you sure you want to delete this account? This action cannot be undone.',

    // Edit/Create
    'back_to_accounts' => 'Back to Accounts',
    'create_new_account' => 'Create New Account',
    'edit_account_title' => 'Edit Account: :name',
    'create_account' => 'Create Account',
    'save_account' => 'Save Changes',

    // Form fields
    'account_information' => 'Account Information',
    'owner' => 'Account Owner',
    'select_owner' => 'Select owner...',
    'email' => 'Email',
    'phone' => 'Phone',
    'address' => 'Address',
    'city' => 'City',
    'state' => 'State/Province',
    'country' => 'Country',
    'postal_code' => 'Postal Code',
    'vat' => 'VAT/Tax ID',

    // Account users
    'account_users' => 'Account Users',
    'account_users_description' => 'Users belonging to this account.',

    // Force password change
    'force_password_change' => 'Require password change on first login',
    'force_password_change_description' => 'Control whether new users in this account must change their password on first login.',
    'force_password_inherit' => 'Inherit from global setting',
    'force_password_inherit_description' => 'Use the global configuration (currently enabled)',
    'force_password_enabled' => 'Always require',
    'force_password_enabled_description' => 'Always require password change for new users in this account',
    'force_password_disabled' => 'Never require',
    'force_password_disabled_description' => 'Never require password change for new users in this account',
];
