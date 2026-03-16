<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt.
    |
    */

    // Form Fields
    'password' => 'Password',
    'enter_password' => 'Enter your password',
    'current_password' => 'Current Password',
    'enter_current_password' => 'Enter current password',
    'new_password' => 'New Password',
    'enter_new_password' => 'Enter new password',
    'confirm_password' => 'Confirm Password',
    'confirm_new_password' => 'Confirm New Password',
    'enter_confirm_password' => 'Confirm your password',

    // Forgot Password Page
    'forgot_password' => 'Forgot Password?',
    'forgot_password_title' => 'Reset Your Password',
    'forgot_password_subtitle' => 'Enter your email address and we\'ll send you a link to reset your password.',
    'forgot_password_message' => 'No worries! Enter your email and we\'ll send you reset instructions.',
    'send_reset_link' => 'Send Reset Link',
    'send_reset_link_loading' => 'Sending...',
    'remember_your_password' => 'Remember your password?',
    'back_to_login' => 'Back to Sign In',

    // Reset Password Page
    'reset_password' => 'Reset Password',
    'set_new_password' => 'Create New Password',
    'set_new_password_message' => 'Choose a strong password to secure your account.',
    'save_new_password' => 'Reset Password',
    'save_password_loading' => 'Resetting...',
    'ensure_both_fields_match' => 'Make sure both passwords match',

    // Password Requirements
    'password_requirements' => 'Password must be at least 8 characters',
    'password_security_note' => 'Use a mix of letters, numbers, and symbols for better security',
    'minimum_8_characters' => 'At least 8 characters',

    // Status Messages
    'reset' => 'Your password has been reset successfully!',
    'reset_link_sent' => 'If an account exists with that email, you will receive a password reset link shortly.',
    'sent' => 'Password reset link sent to your email.',
    'throttled' => 'Please wait before requesting another reset link.',

    // Error Messages
    'user' => "We couldn't find an account with that email address.",
    'token' => 'This password reset link is invalid.',
    'token_invalid' => 'This reset link is invalid. Please request a new one.',
    'token_expired' => 'This reset link has expired. Please request a new one.',
    'token_not_found' => 'This reset link is invalid or has already been used.',
    'reset_error' => 'Unable to reset your password. Please try again.',
    'reset_request_error' => 'Something went wrong. Please try again.',
    'user_not_found' => 'We couldn\'t find an account with that email.',

    // Password Management
    'password_management' => 'Password & Security',
    'manage_password' => 'Manage Password',
    'update_password' => 'Update Password',
    'password_updated' => 'Your password has been updated successfully.',
    'password_updated_login_again' => 'Your password has been changed successfully.',
    'password_change_session_warning' => 'All other active sessions will end after you change the password. Your current session will remain signed in.',
    'set_password_title' => 'Set a Password',
    'set_password_description' => 'You signed up via a social account and don\'t have a password yet. Set one so you can also log in with your email and password.',
    'set_password_button' => 'Set Password',
    'set_password_session_info' => 'After setting a password you can use it to log in alongside your social account.',
    'social_login_no_password_info' => 'You registered using a social login provider and don\'t have a password set. Create a password below to enable email & password login.',
    'generate' => 'Generate Strong Password',

    // Validation
    'password_confirmation_required' => 'Please confirm your password',
    're_enter_password' => 'Re-enter the password for confirmation',

    // Legacy keys for backward compatibility
    'send_password_reset_link' => 'Send Reset Link',
    'confirm_password_message' => 'Please confirm your password before continuing.',
];
