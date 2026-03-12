<?php

return [

    'user_gender' => [
        'male' => [
            'label' => 'Male',
            'value' => 'male',
        ],
        'female' => [
            'label' => 'Female',
            'value' => 'female',
        ],
        'other' => [
            'label' => 'Other',
            'value' => 'other',
        ],
    ],

    'versions' => [
        'patch' => [
            'label' => 'Patch',
            'value' => 'patch',
        ],
        'minor' => [
            'label' => 'Minor',
            'value' => 'minor',
        ],
        'major' => [
            'label' => 'Major',
            'value' => 'major',
        ],
    ],

    'guards' => [
        'web' => [
            'label' => 'Web',
            'value' => 'web',
        ],
        'api' => [
            'label' => 'API',
            'value' => 'api',
        ],
    ],

    'sortable_options' => [
        'created_at' => [
            'label' => 'Created At',
            'slug' => 'created_at',
            'options' => [
                'latest' => 'Newest First',
                'oldest' => 'Oldest First',
            ],
        ],
        'updated_at' => [
            'label' => 'Updated At',
            'slug' => 'updated_at',
            'options' => [
                'latest_updated' => 'Newest First',
                'oldest_updated' => 'Oldest First',
            ],
        ],
    ],

    'date_formats' => [
        'd M Y' => ['label' => 'd M Y (25 Jul 2024)', 'value' => 'd M Y', 'jsformat' => 'DD MM YYYY'],
        'F j, Y' => ['label' => 'F j, Y (July 25, 2024)', 'value' => 'F j, Y', 'jsformat' => 'DD MM YYYY'],
        'd-m-Y' => ['label' => 'd-m-Y (25-07-2024)', 'value' => 'd-m-Y', 'jsformat' => 'DD-MM-YYYY'],
        'm-d-Y' => ['label' => 'm-d-Y (07-25-2024)', 'value' => 'm-d-Y', 'jsformat' => 'MM-DD-YYYY'],
        'Y-m-d' => ['label' => 'Y-m-d (2024-07-25)', 'value' => 'Y-m-d', 'jsformat' => 'YYYY-MM-DD'],
        'm/d/Y' => ['label' => 'm/d/Y (07/25/2024)', 'value' => 'm/d/Y', 'jsformat' => 'MM/DD/YYYY'],
        'd/m/Y' => ['label' => 'd/m/Y (25/07/2024)', 'value' => 'd/m/Y', 'jsformat' => 'DD/MM/YYYY'],
        'Y/m/d' => ['label' => 'Y/m/d (2024/07/25)', 'value' => 'Y/m/d', 'jsformat' => 'YYYY/MM/DD'],
    ],

    'time_formats' => [
        'g:i a' => ['label' => 'g:i a (1:45 pm)', 'value' => 'g:i a', 'jsformat' => 'h:mm a'],
        'g:i A' => ['label' => 'g:i A (1:45 PM)', 'value' => 'g:i A', 'jsformat' => 'h:mm A'],
        'H:i' => ['label' => 'H:i (13:45)', 'value' => 'H:i', 'jsformat' => 'HH:mm'],
    ],
    'email_drivers' => [
        'sendmail' => [
            'label' => 'Mail',
            'value' => 'sendmail',
        ],
        'smtp' => [
            'label' => 'SMTP',
            'value' => 'smtp',
        ],
    ],

    'storage_drivers' => [
        'public' => [
            'label' => 'Local',
            'value' => 'public',
        ],
        's3' => [
            'label' => 'S3',
            'value' => 's3',
        ],
        'ftp' => [
            'label' => 'FTP',
            'value' => 'ftp',
        ],
    ],

    'languages' => [
        'en' => [
            'label' => 'English',
            'value' => 'en',
        ],
        'hi' => [
            'label' => 'Hindi',
            'value' => 'hi',
        ],
        'es' => [
            'label' => 'Spanish',
            'value' => 'es',
        ],
        'fr' => [
            'label' => 'French',
            'value' => 'fr',
        ],
        'de' => [
            'label' => 'German',
            'value' => 'de',
        ],
    ],

    'theme_modes' => [
        'light' => [
            'label' => 'Light',
            'value' => 'light',
        ],
        'dark' => [
            'label' => 'Dark',
            'value' => 'dark',
        ],
    ],

    'font_families' => [
        'Source-Sans-3' => [
            'label' => 'Source Sans 3',
            'value' => 'Source-Sans-3',
        ],
        'Lato' => [
            'label' => 'Lato',
            'value' => 'Lato',
        ],
        'Manrope' => [
            'label' => 'Manrope',
            'value' => 'Manrope',
        ],
        'Ubuntu' => [
            'label' => 'Ubuntu',
            'value' => 'Ubuntu',
        ],
        'Poppins' => [
            'label' => 'Poppins',
            'value' => 'Poppins',
        ],
        'Inter' => [
            'label' => 'Inter',
            'value' => 'Inter',
        ],
        'Roboto' => [
            'label' => 'Roboto',
            'value' => 'Roboto',
        ],
        'Open-Sans' => [
            'label' => 'Open Sans',
            'value' => 'Open-Sans',
        ],
        'Merriweather' => [
            'label' => 'Merriweather',
            'value' => 'Merriweather',
        ],
    ],

    'font_weights' => [
        'normal' => 'Normal',
        100 => 'Thin 100',
        200 => 'Extra 200 Light',
        300 => 'Light 300',
        400 => 'Regular 400',
        500 => 'Medium 500',
        600 => 'Semi 600 Bold',
        700 => 'Bold 700',
        800 => 'Extra 800 Bold',
        900 => 'Bold 900',
        'bolder' => 'Bolder',
    ],

    'color_options' => [
        ['label' => 'Primary', 'value' => 'primary'],
        ['label' => 'Secondary', 'value' => 'secondary'],
        ['label' => 'Success', 'value' => 'success'],
        ['label' => 'Danger', 'value' => 'danger'],
        ['label' => 'Warning', 'value' => 'warning'],
        ['label' => 'Info', 'value' => 'info'],
        ['label' => 'Dark', 'value' => 'dark'],
        ['label' => 'Light', 'value' => 'light'],
        ['label' => 'Muted', 'value' => 'muted'],
        ['label' => 'White', 'value' => 'white'],
        ['label' => 'Black', 'value' => 'black'],
    ],

    'form_field_types' => [
        'text' => [
            'label' => 'Text',
            'value' => 'text',
            'attributes' => [
                'placeholder' => ['type' => 'text', 'label' => 'Placeholder'],
                'value' => ['type' => 'text', 'label' => 'Value'],
                'is_hidden' => ['type' => 'switch', 'label' => 'Is Hidden'],
            ],
        ],
        'number' => [
            'label' => 'Number',
            'value' => 'number',
            'attributes' => [
                'placeholder' => ['type' => 'text', 'label' => 'Placeholder'],
                'value' => ['type' => 'text', 'label' => 'Value'],
                'is_hidden' => ['type' => 'switch', 'label' => 'Is Hidden'],
            ],
        ],
        'date' => [
            'label' => 'Date',
            'value' => 'date',
            'attributes' => [
                'placeholder' => ['type' => 'text', 'label' => 'Placeholder'],
                'value' => ['type' => 'text', 'label' => 'Value'],
                'is_hidden' => ['type' => 'switch', 'label' => 'Is Hidden'],
            ],
        ],
        'email' => [
            'label' => 'Email',
            'value' => 'email',
            'attributes' => [
                'placeholder' => ['type' => 'text', 'label' => 'Placeholder'],
                'value' => ['type' => 'email', 'label' => 'Value'],
                'is_hidden' => ['type' => 'switch', 'label' => 'Is Hidden'],
            ],
        ],
        'textarea' => [
            'label' => 'Textarea',
            'value' => 'textarea',
            'attributes' => [
                'placeholder' => ['type' => 'text', 'label' => 'Placeholder'],
                'value' => ['type' => 'textarea', 'label' => 'Value'],
                'rows' => ['type' => 'number', 'label' => 'Rows'],
            ],
        ],
        'textarea_editor' => [
            'label' => 'Rich Text Editor',
            'value' => 'textarea_editor',
            'attributes' => [
                'placeholder' => ['type' => 'text', 'label' => 'Placeholder'],
                'value' => ['type' => 'textarea_editor', 'label' => 'Value'],
            ],
        ],
        'image' => [
            'label' => 'Image',
            'value' => 'image',
            'attributes' => [
                'placeholder' => ['type' => 'text', 'label' => 'Placeholder'],
                'value' => ['type' => 'image', 'label' => 'Value'],
                'alt' => ['type' => 'text', 'label' => 'Alt'],
                'required' => ['type' => 'switch', 'label' => 'Required'],
            ],
        ],
        'video' => [
            'label' => 'Video',
            'value' => 'video',
            'attributes' => [
                'placeholder' => ['type' => 'text', 'label' => 'Placeholder'],
                'value' => ['type' => 'image', 'label' => 'Value'],
                'controls' => ['type' => 'switch', 'label' => 'Controls'],
                'required' => ['type' => 'switch', 'label' => 'Required'],
            ],
        ],
        'color' => [
            'label' => 'Color Picker',
            'value' => 'color',
            'attributes' => [
                'placeholder' => ['type' => 'text', 'label' => 'Placeholder'],
                'value' => ['type' => 'color', 'label' => 'Value'],
                'required' => ['type' => 'switch', 'label' => 'Required'],
            ],
        ],
        'switch' => [
            'label' => 'Switch',
            'value' => 'switch',
            'attributes' => [
                'placeholder' => ['type' => 'text', 'label' => 'Placeholder'],
                'default_checked' => ['type' => 'switch', 'label' => 'Default Checked'],
            ],
        ],
        'checkbox' => [
            'label' => 'Checkbox',
            'value' => 'checkbox',
            'attributes' => [
                'options' => ['type' => 'array', 'label' => 'Options'],
            ],
        ],
        'radio' => [
            'label' => 'Radio',
            'value' => 'radio',
            'attributes' => [
                'options' => ['type' => 'array', 'label' => 'Options'],
            ],
        ],
        'select' => [
            'label' => 'Select',
            'value' => 'select',
            'attributes' => [
                'placeholder' => ['type' => 'text', 'label' => 'Placeholder'],
                'options' => ['type' => 'repeatable', 'label' => 'Options'],
            ],
        ],
    ],

    'certificate_types' => [
        'self_signed' => ['label' => 'Self Signed', 'value' => 'self_signed'],
        'letsencrypt' => ['label' => "Let's Encrypt", 'value' => 'letsencrypt'],
    ],

    'meta_robots_options' => [
        'index, follow' => ['label' => 'Index, Follow', 'value' => 'index, follow'],
        'noindex, follow' => ['label' => 'Noindex, Follow', 'value' => 'noindex, follow'],
        'index, nofollow' => ['label' => 'Index, Nofollow', 'value' => 'index, nofollow'],
        'noindex, nofollow' => ['label' => 'Noindex, Nofollow', 'value' => 'noindex, nofollow'],
    ],
];
