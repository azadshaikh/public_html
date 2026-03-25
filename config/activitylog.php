<?php

use App\Models\ActivityLog;
use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Actions\LogActivityAction;

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    /*
     * When the clean-command is executed, all recording activities older than
     * the number of days specified here will be deleted.
     */
    'clean_after_days' => env('ACTIVITYLOG_CLEAN_AFTER_DAYS', 365),

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => env('ACTIVITYLOG_DEFAULT_LOG_NAME', 'default'),

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the current Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * If set to true, the subject returns soft deleted models.
     */
    'include_soft_deleted_subjects' => env('ACTIVITYLOG_INCLUDE_SOFT_DELETED_SUBJECTS', false),

    /*
     * This model will be used to log activity.
     * It should implement the Spatie\Activitylog\Contracts\Activity interface
     * and extend Illuminate\Database\Eloquent\Model.
     */
    'activity_model' => ActivityLog::class,

    /*
     * These attributes will be excluded from logging for all models.
     */
    'default_except_attributes' => [],

    /*
     * Buffered activity logging can reduce write volume during busy requests.
     */
    'buffer' => [
        'enabled' => env('ACTIVITYLOG_BUFFER_ENABLED', false),
    ],

    /*
     * Action classes can be swapped to customize how activities are written and cleaned.
     */
    'actions' => [
        'log_activity' => LogActivityAction::class,
        'clean_log' => CleanActivityLogAction::class,
    ],

    /*
     * Retained for the historical v4 migration that still reads these config values.
     */
    'table_name' => 'activity_log',
    'database_connection' => null,
];
