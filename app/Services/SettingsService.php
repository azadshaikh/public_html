<?php

namespace App\Services;

use App\Models\Settings;
use Illuminate\Database\Eloquent\Model;

class SettingsService
{
    /**
     * Update or create a setting.
     * The `created_by` and `updated_by` are hardcoded to 1 as this service
     * is expected to be used by console commands where no user is authenticated.
     *
     * @param  mixed  $value
     * @return Model|Settings|null
     */
    public function updateSetting(string $key, $value)
    {
        if (empty($value) || $value === 'null') {
            return null;
        }

        return Settings::query()->updateOrCreate(['key' => $key], [
            'value' => $value,
            'created_by' => 1,
            'updated_by' => 1,
        ]);
    }

    /**
     * Update environment variable
     */
    public function updateEnvironmentVariable(string $key, string $value): void
    {
        $envFile = app()->environmentFilePath();
        $envContent = file_get_contents($envFile);

        // Escape special characters in the key for regex
        $escapedKey = preg_quote($key, '/');

        if (preg_match(sprintf('/^%s=.*$/m', $escapedKey), $envContent)) {
            $envContent = preg_replace(sprintf('/^%s=.*$/m', $escapedKey), sprintf('%s=%s', $key, $value), $envContent);
        } else {
            $envContent .= sprintf('%s%s=%s', PHP_EOL, $key, $value);
        }

        file_put_contents($envFile, $envContent);
    }
}
