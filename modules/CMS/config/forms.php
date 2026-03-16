<?php

return [
    'name' => 'Forms',

    /*
    |--------------------------------------------------------------------------
    | Form Templates
    |--------------------------------------------------------------------------
    |
    | Pre-defined form templates for quick form creation.
    |
    */
    'templates' => [
        'default' => [
            'label' => 'Blank Form',
            'value' => 'default',
            'description' => 'Start with a blank form',
            'icon' => 'ri-file-text-line',
        ],
        'contact' => [
            'label' => 'Contact Form',
            'value' => 'contact',
            'description' => 'Simple contact form with name, email, and message',
            'icon' => 'ri-mail-line',
        ],
        'newsletter' => [
            'label' => 'Newsletter Signup',
            'value' => 'newsletter',
            'description' => 'Email subscription form',
            'icon' => 'ri-newspaper-line',
        ],
        'registration' => [
            'label' => 'User Registration',
            'value' => 'registration',
            'description' => 'User registration form with password',
            'icon' => 'ri-user-add-line',
        ],
        'feedback' => [
            'label' => 'Feedback Form',
            'value' => 'feedback',
            'description' => 'Collect user feedback and ratings',
            'icon' => 'ri-chat-smile-line',
        ],
        'survey' => [
            'label' => 'Survey',
            'value' => 'survey',
            'description' => 'Multi-question survey form',
            'icon' => 'ri-questionnaire-line',
        ],
        'quote' => [
            'label' => 'Quote Request',
            'value' => 'quote',
            'description' => 'Request for quote/estimate',
            'icon' => 'ri-money-dollar-circle-line',
        ],
        'booking' => [
            'label' => 'Booking/Reservation',
            'value' => 'booking',
            'description' => 'Appointment or reservation booking',
            'icon' => 'ri-calendar-check-line',
        ],
        'payment' => [
            'label' => 'Payment Form',
            'value' => 'payment',
            'description' => 'Form with payment integration',
            'icon' => 'ri-bank-card-line',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Types
    |--------------------------------------------------------------------------
    |
    | Different form display types and behaviors.
    |
    */
    'form_types' => [
        'standard' => [
            'label' => 'Standard Form',
            'value' => 'standard',
            'description' => 'Traditional form with all fields visible',
        ],
        'multi_step' => [
            'label' => 'Multi-Step Form',
            'value' => 'multi_step',
            'description' => 'Form split into multiple steps/pages',
        ],
        'conversational' => [
            'label' => 'Conversational Form',
            'value' => 'conversational',
            'description' => 'One question at a time, chat-like experience',
        ],
        'popup' => [
            'label' => 'Popup Form',
            'value' => 'popup',
            'description' => 'Form displayed in a modal/popup',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Types
    |--------------------------------------------------------------------------
    |
    | Available form field types for the drag-and-drop builder.
    |
    */
    'field_types' => [
        // Basic Fields
        'text' => [
            'label' => 'Single Line Text',
            'group' => 'basic',
            'icon' => 'ri-text',
            'supports' => ['placeholder', 'default_value', 'required', 'validation'],
        ],
        'textarea' => [
            'label' => 'Paragraph Text',
            'group' => 'basic',
            'icon' => 'ri-file-text-line',
            'supports' => ['placeholder', 'default_value', 'required', 'rows'],
        ],
        'email' => [
            'label' => 'Email',
            'group' => 'basic',
            'icon' => 'ri-mail-line',
            'supports' => ['placeholder', 'default_value', 'required', 'validation'],
        ],
        'number' => [
            'label' => 'Number',
            'group' => 'basic',
            'icon' => 'ri-hashtag',
            'supports' => ['placeholder', 'default_value', 'required', 'min', 'max', 'step'],
        ],
        'phone' => [
            'label' => 'Phone',
            'group' => 'basic',
            'icon' => 'ri-phone-line',
            'supports' => ['placeholder', 'default_value', 'required', 'format'],
        ],
        'url' => [
            'label' => 'Website URL',
            'group' => 'basic',
            'icon' => 'ri-links-line',
            'supports' => ['placeholder', 'default_value', 'required', 'validation'],
        ],

        // Choice Fields
        'select' => [
            'label' => 'Dropdown',
            'group' => 'choice',
            'icon' => 'ri-arrow-down-s-line',
            'supports' => ['choices', 'default_value', 'required', 'multiple'],
        ],
        'radio' => [
            'label' => 'Multiple Choice',
            'group' => 'choice',
            'icon' => 'ri-radio-button-line',
            'supports' => ['choices', 'default_value', 'required', 'layout'],
        ],
        'checkbox' => [
            'label' => 'Checkboxes',
            'group' => 'choice',
            'icon' => 'ri-checkbox-line',
            'supports' => ['choices', 'default_value', 'required'],
        ],

        // Advanced Fields
        'date' => [
            'label' => 'Date Picker',
            'group' => 'advanced',
            'icon' => 'ri-calendar-line',
            'supports' => ['default_value', 'required', 'date_format', 'min_date', 'max_date'],
        ],
        'time' => [
            'label' => 'Time Picker',
            'group' => 'advanced',
            'icon' => 'ri-time-line',
            'supports' => ['default_value', 'required', 'time_format'],
        ],
        'file_upload' => [
            'label' => 'File Upload',
            'group' => 'advanced',
            'icon' => 'ri-upload-cloud-line',
            'supports' => ['required', 'allowed_types', 'max_size', 'multiple'],
        ],
        'rating' => [
            'label' => 'Rating',
            'group' => 'advanced',
            'icon' => 'ri-star-line',
            'supports' => ['required', 'max_rating', 'icon_style'],
        ],
        'hidden' => [
            'label' => 'Hidden Field',
            'group' => 'advanced',
            'icon' => 'ri-eye-off-line',
            'supports' => ['default_value'],
        ],

        // Layout Fields
        'html' => [
            'label' => 'HTML Block',
            'group' => 'layout',
            'icon' => 'ri-code-line',
            'supports' => ['content'],
        ],
        'divider' => [
            'label' => 'Section Divider',
            'group' => 'layout',
            'icon' => 'ri-separator',
            'supports' => [],
        ],
        'page_break' => [
            'label' => 'Page Break',
            'group' => 'layout',
            'icon' => 'ri-file-copy-line',
            'supports' => ['title', 'description'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Submission Statuses
    |--------------------------------------------------------------------------
    */
    'submission_statuses' => [
        'unread' => [
            'label' => 'Unread',
            'value' => 'unread',
            'color' => 'primary',
        ],
        'read' => [
            'label' => 'Read',
            'value' => 'read',
            'color' => 'success',
        ],
        'starred' => [
            'label' => 'Starred',
            'value' => 'starred',
            'color' => 'warning',
        ],
        'spam' => [
            'label' => 'Spam',
            'value' => 'spam',
            'color' => 'danger',
        ],
        'trash' => [
            'label' => 'Trash',
            'value' => 'trash',
            'color' => 'secondary',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam Protection
    |--------------------------------------------------------------------------
    */
    'spam_protection' => [
        'honeypot' => [
            'label' => 'Honeypot',
            'enabled' => true,
            'description' => 'Hidden field to catch bots',
        ],
        'captcha' => [
            'label' => 'Google reCAPTCHA',
            'enabled' => true,
            'description' => 'Google reCAPTCHA v2/v3',
        ],
        'akismet' => [
            'label' => 'Akismet',
            'enabled' => false,
            'description' => 'Akismet spam filtering service',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        'mailchimp' => [
            'label' => 'Mailchimp',
            'enabled' => false,
        ],
        'slack' => [
            'label' => 'Slack',
            'enabled' => false,
        ],
        'zapier' => [
            'label' => 'Zapier',
            'enabled' => false,
        ],
        'webhook' => [
            'label' => 'Custom Webhook',
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */
    'validation_rules' => [
        'email' => 'Must be a valid email address',
        'url' => 'Must be a valid URL',
        'number' => 'Must be a number',
        'phone' => 'Must be a valid phone number',
        'required' => 'This field is required',
        'min_length' => 'Must be at least :min characters',
        'max_length' => 'Must not exceed :max characters',
        'min_value' => 'Must be at least :min',
        'max_value' => 'Must not exceed :max',
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_file_size' => 10240, // KB (10MB)
        'max_submissions_per_ip_per_day' => 10,
        'max_submissions_per_form_per_hour' => 100,
        'entries_per_page' => 20,
    ],
];
