<?php

return [
    'types' => [
        'trial' => ['label' => 'Trial',    'value' => 'trial',    'color' => 'warning'],
        'internal' => ['label' => 'Internal', 'value' => 'internal', 'color' => 'info'],
        'free' => ['label' => 'Free',     'value' => 'free',     'color' => 'primary'],
        'paid' => ['label' => 'Paid',     'value' => 'paid',     'color' => 'success'],
        'special' => ['label' => 'Special',  'value' => 'special',  'color' => 'info'],
    ],

    'statuses' => [
        'provisioning' => ['label' => 'Provisioning', 'value' => 'provisioning', 'color' => 'info'],
        'waiting_for_dns' => ['label' => 'Waiting for DNS', 'value' => 'waiting_for_dns', 'color' => 'warning'],
        'active' => ['label' => 'Active',    'value' => 'active',    'color' => 'success'],
        'failed' => ['label' => 'Failed',    'value' => 'failed',    'color' => 'danger'],
        'suspended' => ['label' => 'Suspended', 'value' => 'suspended', 'color' => 'danger'],
        'expired' => ['label' => 'Expired',   'value' => 'expired',   'color' => 'danger'],
        'trash' => ['label' => 'Trash',     'value' => 'trash',     'color' => 'danger'],
        'deleted' => ['label' => 'Deleted',   'value' => 'deleted',   'color' => 'danger'],
    ],

    'providers' => [
        'hestia' => ['label' => 'HestiaCp', 'value' => 'hestia', 'color' => 'primary'],
    ],

    'steps' => [
        // ── PHASE A: Origin Infrastructure (runs immediately for all domain types) ──
        'resolve_domain' => [
            'title' => 'Resolve Domain Record',
            'info' => 'Check if the root domain exists in the system and associate or create it.',
            'status' => 'pending',
            'command' => 'platform:resolve-domain',
        ],
        'create_user' => [
            'title' => 'Create Server User',
            'info' => 'Create a new system user on the Hestia server.',
            'status' => 'pending',
            'command' => 'platform:hestia:create-user',
        ],
        'create_website' => [
            'title' => 'Create Web Domain',
            'info' => 'Create the web domain entry in Hestia.',
            'status' => 'pending',
            'command' => 'platform:hestia:create-website',
        ],
        'create_database' => [
            'title' => 'Create Database',
            'info' => 'Create the database and user.',
            'status' => 'pending',
            'command' => 'platform:hestia:create-database',
        ],

        // ── PHASE B: CDN creation + DNS Verification ──
        // CDN pull zone must be created before verify_dns so that external-mode
        // customers receive CNAME-to-CDN instructions rather than A-to-origin.
        'setup_bunny_cdn' => [
            'title' => 'Setup CDN',
            'info' => 'Create Bunny pull zone and register website domain as CDN hostname.',
            'status' => 'pending',
            'command' => 'platform:bunny:setup-cdn',
        ],
        'verify_dns' => [
            'title' => 'Verify Domain DNS',
            'info' => 'Wait for DNS propagation before provisioning CDN/SSL.',
            'status' => 'pending',
            'command' => 'platform:dns:verify-step',
        ],

        // ── PHASE C: DNS Records + SSL (runs after DNS is confirmed) ──
        'setup_bunny_dns' => [
            'title' => 'Configure DNS Records',
            'info' => 'Create DNS CNAME records routing domain through Bunny CDN.',
            'status' => 'pending',
            'command' => 'platform:bunny:setup-dns',
        ],
        'issue_ssl' => [
            'title' => 'Issue SSL Certificate',
            'info' => 'Request wildcard SSL via acme.sh DNS-01 challenge on the Hestia server, unless ACME issuance is skipped for local/LAN provisioning.',
            'status' => 'pending',
            'command' => 'platform:ssl:issue-certificate',
        ],
        'install_ssl' => [
            'title' => 'Install SSL on Origin',
            'info' => 'Install the wildcard certificate on the Hestia origin server.',
            'status' => 'pending',
            'command' => 'platform:hestia:install-ssl',
        ],
        'configure_cdn_ssl' => [
            'title' => 'Configure CDN SSL',
            'info' => 'Upload wildcard cert to Bunny CDN and enforce HTTPS at edge.',
            'status' => 'pending',
            'command' => 'platform:bunny:configure-cdn-ssl',
        ],

        // ── PHASE D: Application Setup (unchanged) ──
        'prepare_astero' => [
            'title' => 'Prepare Astero',
            'info' => 'Extract Astero release and setup shared directories.',
            'status' => 'pending',
            'command' => 'platform:hestia:prepare-astero',
        ],
        'configure_env' => [
            'title' => 'Configure Environment',
            'info' => 'Create and configure the .env file for Astero.',
            'status' => 'pending',
            'command' => 'platform:hestia:configure-env',
        ],
        'install_astero' => [
            'title' => 'Install Astero',
            'info' => 'Run Astero installation and setup scheduler.',
            'status' => 'pending',
            'command' => 'platform:hestia:install-astero',
        ],
        'send_emails' => [
            'title' => 'Send Welcome Emails',
            'info' => 'Send login credentials to website admin and super user.',
            'status' => 'pending',
            'command' => 'platform:send-provisioning-emails',
        ],
    ],

    'niches' => [
        'restaurant' => ['label' => 'Restaurant', 'value' => 'restaurant'],
        'law_firm' => ['label' => 'Law Firm', 'value' => 'law_firm'],
        'real_estate' => ['label' => 'Real Estate', 'value' => 'real_estate'],
        'healthcare' => ['label' => 'Healthcare', 'value' => 'healthcare'],
        'dental' => ['label' => 'Dental', 'value' => 'dental'],
        'fitness' => ['label' => 'Fitness & Gym', 'value' => 'fitness'],
        'salon' => ['label' => 'Salon & Spa', 'value' => 'salon'],
        'automotive' => ['label' => 'Automotive', 'value' => 'automotive'],
        'plumbing' => ['label' => 'Plumbing', 'value' => 'plumbing'],
        'hvac' => ['label' => 'HVAC', 'value' => 'hvac'],
        'roofing' => ['label' => 'Roofing', 'value' => 'roofing'],
        'landscaping' => ['label' => 'Landscaping', 'value' => 'landscaping'],
        'cleaning' => ['label' => 'Cleaning Services', 'value' => 'cleaning'],
        'photography' => ['label' => 'Photography', 'value' => 'photography'],
        'ecommerce' => ['label' => 'E-commerce', 'value' => 'ecommerce'],
        'blog' => ['label' => 'Blog', 'value' => 'blog'],
        'portfolio' => ['label' => 'Portfolio', 'value' => 'portfolio'],
        'agency' => ['label' => 'Agency', 'value' => 'agency'],
        'startup' => ['label' => 'Startup', 'value' => 'startup'],
        'non_profit' => ['label' => 'Non-Profit', 'value' => 'non_profit'],
        'education' => ['label' => 'Education', 'value' => 'education'],
        'event' => ['label' => 'Event', 'value' => 'event'],
        'other' => ['label' => 'Other', 'value' => 'other'],
    ],
];
