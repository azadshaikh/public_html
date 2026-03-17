<?php

namespace Modules\Platform\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Modules\Platform\Models\Secret;

trait HasSecrets
{
    /**
     * Get all secrets for this model.
     */
    public function secrets(): MorphMany
    {
        return $this->morphMany(Secret::class, 'secretable');
    }

    /**
     * Get a specific secret by key - returns array with username, value and metadata.
     */
    public function getSecret(string $key): ?array
    {
        /** @var Secret|null $secret */
        $secret = $this->secrets()->where('key', $key)->first();

        if (! $secret) {
            return null;
        }

        return [
            'username' => $secret->username,
            'value' => $secret->decrypted_value,
            'metadata' => $secret->metadata ?? [],
            'type' => $secret->type,
            'key' => $secret->key,
        ];
    }

    /**
     * Get just the decrypted value for a secret.
     */
    public function getSecretValue(string $key): ?string
    {
        /** @var Secret|null $secret */
        $secret = $this->secrets()->where('key', $key)->first();

        return $secret?->decrypted_value;
    }

    /**
     * Get just the decrypted username for a secret.
     */
    public function getSecretUsername(string $key): ?string
    {
        /** @var Secret|null $secret */
        $secret = $this->secrets()->where('key', $key)->first();

        return $secret?->username;
    }

    /**
     * Set a secret value with optional username.
     *
     * @param  string  $key  The secret key identifier
     * @param  string  $value  The secret value (password) to encrypt
     * @param  string  $type  The type of secret (default: 'password')
     * @param  string|null  $username  Optional username to encrypt and store
     * @param  array|null  $metadata  Optional metadata array
     */
    public function setSecret(string $key, string $value, string $type = 'password', ?string $username = null, ?array $metadata = null): Secret
    {
        $data = [
            'value' => encrypt($value),
            'type' => $type,
            'metadata' => $metadata,
            'is_active' => true,
            'created_by' => Auth::id(),
        ];

        if ($username !== null) {
            $data['username'] = $username;
        }

        /** @var Secret $secret */
        $secret = $this->secrets()->updateOrCreate(
            ['key' => $key],
            $data
        );

        return $secret;
    }

    /**
     * Delete a secret by key.
     */
    public function deleteSecret(string $key): bool
    {
        return $this->secrets()->where('key', $key)->delete() > 0;
    }
}
