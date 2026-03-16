<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user.
    |
    */

    // General
    'login' => 'Sign In',
    'register' => 'Create Account',
    'logout' => 'Sign Out',
    'sign_in' => 'Sign In',
    'sign_out' => 'Sign Out',
    'or' => 'or',
    'and' => 'and',
    'sending' => 'Sending...',

    // Login Page
    'welcome_back' => 'Welcome Back',
    'login_title' => 'Welcome Back',
    'login_subtitle' => 'Enter your credentials to access your account',
    'login_button' => 'Sign In',
    'login_loading' => 'Signing in...',
    'signing_in' => 'Signing in...',
    'remember_me' => 'Remember me',
    'no_account' => "Don't have an account?",
    'sign_up_link' => 'Create one',

    // Registration Page
    'register_title' => 'Create Your Account',
    'register_subtitle' => 'Fill in your details to get started',
    'register_button' => 'Create Account',
    'register_loading' => 'Creating account...',
    'creating_account' => 'Creating account...',
    'already_have_an_account' => 'Already have an account?',
    'already_have_account' => 'Already have an account?',
    'sign_in_link' => 'Sign in',

    // Form Fields
    'email_address' => 'Email Address',
    'enter_email_address' => 'you@example.com',
    'full_name' => 'Full Name',
    'enter_full_name' => 'John Doe',

    // Terms
    'i_agree_to_the' => 'I agree to the',
    'terms_of_service' => 'Terms of Service',
    'privacy_policy' => 'Privacy Policy',

    // Social Login
    'or_continue_with' => 'Or continue with',
    'continue_with' => 'Or continue with',
    'continue_google' => 'Google',
    'continue_github' => 'GitHub',
    'continue_twitter' => 'X',
    'continue_with_google' => 'Google',
    'continue_with_github' => 'GitHub',
    'continue_with_twitter' => 'X',
    'social_auth_loading' => 'Processing...',
    'complete_profile_title' => 'Complete Your Profile',
    'complete_profile_subtitle' => 'Please enter your first and last name to continue.',
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'enter_first_name' => 'Enter first name',
    'enter_last_name' => 'Enter last name',
    'save_and_continue' => 'Save & Continue',

    // Email Verification
    'verify_email' => 'Verify Your Email',
    'verify_email_title' => 'Check Your Inbox',
    'verify_email_address' => 'Verify Your Email Address',
    'verify_email_address_message' => "We've sent a verification link to your email address. Click the link to verify your account.",
    'verify_email_subtitle' => "We've sent a verification link to your email address. Click the link to verify your account.",
    'verify_email_check_spam' => "Can't find the email? Check your spam folder.",
    'resend_verification' => 'Resend Verification Email',
    'resend_verify_email_link' => 'Resend Verification Email',
    'resend_loading' => 'Sending...',
    'verification_sent' => "We've sent a new verification link to your email.",
    'email_verified_success' => 'Your email has been verified successfully!',
    'email_already_verified' => 'Your email is already verified.',
    'verification_required_message' => 'Please verify your email address to continue. Check your inbox for the verification link.',
    'verification_resend_prompt' => 'Click below to request a new verification email.',

    // Authentication Errors
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'throttle_detailed' => 'Too many login attempts. Please wait :time before trying again, or reset your password.',
    'attempts_remaining_warning' => 'Warning: You have :count attempt remaining before your account is temporarily locked.|Warning: You have :count attempts remaining before your account is temporarily locked.',
    'social_auth_disabled' => 'Social authentication is not available.',
    'invalid_provider' => 'Invalid authentication provider.',
    'provider_disabled' => 'This authentication provider is not currently available.',
    'social_login_failed' => 'Unable to authenticate with the selected provider. Please try again.',

    // Account Status
    'account_suspended' => 'Your account has been suspended. Please contact support for assistance.',
    'account_banned' => 'Your account has been banned. Please contact support for assistance.',
    'account_pending_approval' => 'Your account is pending approval. You will be notified once an administrator reviews your registration.',
    'account_inactive' => 'Your account is not active. Please contact support.',

    // Registration Status
    'registration_success' => 'Account created successfully!',
    'registration_failed' => 'We couldn\'t create your account. Please try again.',
    'registration_pending_approval' => 'Your registration has been received! An administrator will review your account shortly.',
    'registration_welcome' => 'Welcome! Your account is ready to use.',

    // Social Login
    'social_login_failed' => 'Unable to sign in with the selected provider. Please try again.',

    // Time formatting for rate limiting
    'time_seconds' => ':count seconds',
    'time_minutes' => ':count minute|:count minutes',
    'time_hours' => ':count hour|:count hours',
    'time_hours_minutes' => ':hours hour(s) and :minutes minute(s)',

    // Validation Messages
    'validation' => [
        'email_required' => 'Please enter your email address.',
        'email_invalid' => 'Please enter a valid email address.',
        'email_too_long' => 'Email address is too long.',
        'password_required' => 'Please enter your password.',
        'name_required' => 'Please enter your name.',
    ],

    // Two-Factor Authentication
    'two_factor_authentication' => 'Two-Factor Authentication',
    'authenticator_app' => 'Authenticator App',
    'authenticator_app_description' => 'Use an authenticator app for secure verification codes.',
    'sms_authentication' => 'SMS Authentication',
    'sms_authentication_description' => 'Receive verification codes via text message.',
    'enable_two_factor' => 'Enable Two-Factor Authentication',
    'disable_two_factor' => 'Disable Two-Factor Authentication',
    'two_factor_is_enabled' => 'Two-factor authentication is enabled for your account.',
    'two_factor_already_enabled' => 'Two-factor authentication is already enabled.',
    'two_factor_not_enabled' => 'Two-factor authentication is not enabled.',
    'two_factor_setup_started' => 'Two-factor setup started. Add the setup key to your authenticator app, then confirm with a code.',
    'two_factor_setup_pending' => 'Two-factor setup is pending confirmation. Add the setup key to your authenticator app and enter a valid code below.',
    'two_factor_setup_not_found' => 'No pending two-factor setup was found. Please start setup again.',
    'two_factor_enabled_successfully' => 'Two-factor authentication has been enabled successfully.',
    'two_factor_disabled_successfully' => 'Two-factor authentication has been disabled successfully.',
    'recovery_codes_regenerated_successfully' => 'Recovery codes were regenerated successfully.',
    'invalid_two_factor_code' => 'The authentication code or recovery code is invalid.',
    'setup_key' => 'Setup Key',
    'scan_qr_code' => 'Scan QR Code',
    'otpauth_url' => 'Manual Setup URL',
    'authentication_code' => 'Authentication Code',
    'enter_authentication_code' => 'Enter 6-digit authentication code',
    'confirm_and_enable' => 'Confirm & Enable',
    'cancel_two_factor_setup' => 'Cancel Setup',
    'recovery_codes' => 'Recovery Codes',
    'confirm_password_to_manage_recovery_codes' => 'Enter your current password to view or regenerate recovery codes.',
    'loading_recovery_codes' => 'Loading...',
    'show_recovery_codes' => 'Show Recovery Codes',
    'regenerate_recovery_codes' => 'Regenerate Recovery Codes',
    'no_recovery_codes_available' => 'No recovery codes are available.',
    'backup_codes_heading' => 'Print or download your backup codes',
    'copy_codes' => 'Copy',
    'codes_copied' => 'Codes copied to clipboard.',
    'print_codes' => 'Print',
    'before_you_continue' => 'Before you continue',
    'scan_qr_code_description' => 'Use your authenticator app to scan the QR code below.',
    'two_factor_status' => 'Two-factor authentication status:',
    'authentication_methods' => 'Authentication Method',
    'default_authenticator_method' => 'Authenticator app',
    'mobile_device' => 'Mobile device',
    'recovery_codes_hidden_for_security' => 'For security, existing recovery codes cannot be viewed again. Generate new codes when needed.',
    'store_recovery_codes_notice' => 'These recovery codes are shown only once. Save them now in a secure place.',
    'two_factor_enabled_description' => 'Two-factor authentication is active. Keep your authenticator app available for future sign-ins.',
    'two_factor_challenge_description' => 'Enter the 6-digit code from your authenticator app to continue signing in.',
    'enter_authentication_or_recovery_code' => 'Enter authentication or recovery code',
    'recovery_code_hint' => 'You can also use one of your recovery codes.',
    'verify_and_continue' => 'Verify & Continue',
    'continue' => 'Continue',
    'signing_in_as' => 'Signing in as',
    'password_required' => 'Please enter your current password.',
    'password_verification_failed' => 'Unable to verify your password. Please try again.',
    'verifying_password' => 'Verifying...',
    'confirm_password_title' => 'Confirm your password',
    'confirm_password_context' => 'Enter your current account password to continue.',
    'reveal_secret' => 'Reveal Secret',
    'secret_password_context' => 'Enter your current account password to reveal this secret.',

    // Support
    'need_help' => 'Need help?',
    'contact_support' => 'Contact Support',

    // Legacy keys for backward compatibility
    'login_your_account' => 'Sign In to Your Account',
    'create_account' => 'Create Account',
    'create_an_account' => 'Create Your Account',
    'by_logging_in' => 'By signing in, you agree to our',
    'by_registering' => 'By creating an account, you agree to our',
    'new_verification_link_sent' => 'A new verification link has been sent to your email.',
    'email_marked_verified' => 'Email address marked as verified.',
];
