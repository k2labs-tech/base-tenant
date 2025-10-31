<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // Two Factor Authentication
    '2fa' => [
        'title' => 'Two-Factor Authentication',
        'status_enabled' => 'Two-factor authentication is currently enabled.',
        'status_disabled' => 'Two-factor authentication is currently disabled.',
        'enable' => 'Enable',
        'disable' => 'Disable',
        'setup_title' => 'Set Up Two-Factor Authentication',
        'setup_step1' => 'Scan the QR code below with your authenticator app:',
        'setup_step2' => 'Enter the 6-digit code from your authenticator app:',
        'manual_entry' => 'Or enter this code manually:',
        'verification_code' => 'Verification Code',
        'confirm_password' => 'Confirm Password',
        'disable_confirmation_title' => 'Disable Two-Factor Authentication',
        'disable_confirmation_description' => 'Are you sure you want to disable two-factor authentication? This will make your account less secure.',
        'recovery_codes_title' => 'Recovery Codes',
        'recovery_codes_description' => 'Store these recovery codes in a secure location. They can be used to access your account if you lose access to your authenticator device.',
        'recovery_codes_warning' => 'These codes will only be displayed once. Store them securely.',
        'view_recovery_codes' => 'View Recovery Codes',
        'regenerate_codes' => 'Regenerate Codes',
        'download_codes' => 'Download Codes',
        'codes_saved' => 'I have saved my codes',
        'verification_title' => 'Two-Factor Authentication',
        'verification_description' => 'Please enter the authentication code from your authenticator app to continue.',
        'recovery_description' => 'Please enter one of your recovery codes to continue.',
        'recovery_code' => 'Recovery Code',
        'use_recovery_code' => 'Use recovery code',
        'use_authentication_code' => 'Use authentication code',
        'verify' => 'Verify',
        'back_to_login' => 'Back to Login',
        'invalid_code' => 'The verification code is invalid.',
        'enabled_title' => 'Two-Factor Authentication Enabled',
        'enabled_description' => 'Your account is now protected with two-factor authentication.',
        'disabled_title' => 'Two-Factor Authentication Disabled',
        'disabled_description' => 'Two-factor authentication has been disabled for your account.',
        'recovery_codes_regenerated' => 'Recovery codes have been regenerated successfully.',
    ],

];
