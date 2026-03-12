<?php

use App\Models\Monitor;

return [
    // Set the table to be used for monitoring data.
    'table' => 'queue_monitor',
    'connection' => null,

    /*
     * Set the model used for monitoring.
     * If using a custom model, be sure to implement the
     *   App\Models\Contracts\MonitorContract
     * interface or extend the base model.
     */
    'model' => Monitor::class,

    // Determined if the queued jobs should be monitored
    'monitor_queued_jobs' => true,

    // Specify the max character length to use for storing exception backtraces.
    'db_max_length_exception' => 4294967295,
    'db_max_length_exception_message' => 65535,

    // The optional UI settings.
    'ui' => [
        // Enable the UI
        'enabled' => true,

        // Set the monitored jobs count to be displayed per page.
        'per_page' => 35,

        // Show custom data stored on model
        'show_custom_data' => false,

        // Allow the deletion of single monitor items.
        'allow_deletion' => true,

        // Allow retry for a single failed monitor item.
        'allow_retry' => true,

        // Allow purging all monitor entries.
        'allow_purge' => true,

        'show_metrics' => true,

        // Time frame used to calculate metrics values (in days).
        'metrics_time_frame' => 14,

        // The interval before refreshing the dashboard (in seconds).
        'refresh_interval' => 10,

        // Order the queued but not started jobs first
        'order_queued_first' => false,
    ],

    // -------------------------------------------------------------------------
    // Alert settings — trigger notifications when failures spike.
    // -------------------------------------------------------------------------
    'alerts' => [
        // Master switch.
        'enabled' => true,

        // Number of failures within the rolling window to trigger an alert.
        'failure_threshold' => 5,

        // Rolling window in minutes for counting failures.
        'failure_window_minutes' => 10,

        // Silence window in minutes — suppress repeat alerts for the same queue.
        'silence_minutes' => 60,
    ],

    // -------------------------------------------------------------------------
    // Throughput snapshots — hourly aggregation for charts (Feature 7).
    // -------------------------------------------------------------------------
    'snapshots' => [
        // Master switch. Set to false to disable charts and the aggregate command.
        'enabled' => true,

        // How many hours to show in the throughput chart (default: 24).
        'chart_hours' => 24,

        // How many days to retain snapshot rows before pruning (default: 30).
        'retention_days' => 30,
    ],

    // -------------------------------------------------------------------------
    // Worker / supervisor monitoring — process panel (Feature 8).
    // -------------------------------------------------------------------------
    'workers' => [
        // Master switch. Set to false to hide the workers panel.
        'enabled' => true,

        // Directory containing Supervisor .conf files.
        // Each managed app has a file named {username}.conf in this directory.
        'supervisor_conf_dir' => '/etc/supervisor/conf.d',

        // How often the workers panel auto-refreshes (seconds). null = disabled.
        'worker_refresh_interval' => 30,
    ],
];
