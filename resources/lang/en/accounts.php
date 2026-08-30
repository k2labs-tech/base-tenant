<?php

return [
    // Management
    'management_title' => 'Account Management',
    'management_description' => 'Every account you can reach, with its plan and how many people are in it.',
    'created' => 'Created',
    'empty_description' => 'Create the first account to start working.',
    'filter_subscription' => 'Subscription',
    'all_subscriptions' => 'Any subscription',
    'subscription_active' => 'Active',
    'subscription_trialling' => 'Trialling',
    'subscription_none' => 'No subscription',
    'add_new' => 'Add New Account',
    'search_placeholder' => 'Search accounts...',

    // Table headers
    'name' => 'Account Name',
    'users_count' => 'Users',
    'status' => 'Status',
    'actions' => 'Actions',
    'owner_short' => 'Owner',
    'no_owner' => 'Unassigned',
    'no_email' => 'No contact email',
    'inactive_summary' => '{1} :count account is inactive and nobody can work inside it|[2,*] :count accounts are inactive and nobody can work inside them',

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
    'edit_account_description' => 'Everything on this page is saved together. The people in the account are listed below, but they are edited from their own page.',
    'account_information_description' => 'The name the account is known by, who owns it, and whether it can be used at all.',
    'contact_details' => 'Contact and Billing',
    'contact_details_description' => 'Where the account is reached and what goes on its invoices. None of it is required.',
    'active_description' => 'An inactive account keeps all of its data but nobody can work inside it.',
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
    'no_account' => 'No account',
    'staff' => 'Staff',
    'staff_entered' => ':name entered this account from the platform administration.',
    'none' => 'No accounts',
    'leave' => 'Leave this account',

];
