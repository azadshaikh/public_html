<?php

namespace App\Traits;

trait HasMetadata
{
    /**
     * Get a specific value from the metadata JSON column
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadataArray(), $key, $default);
    }

    /**
     * Set a specific value in the metadata JSON column
     */
    public function setMetadata(string $key, mixed $value): self
    {
        $metadata = $this->metadataArray();
        data_set($metadata, $key, $value);
        $this->writeMetadata($metadata);

        return $this;
    }

    /**
     * Check if a key exists in the metadata JSON column
     */
    public function hasMetadata(string $key): bool
    {
        return data_get($this->metadataArray(), $key) !== null;
    }

    /**
     * Remove a key from the metadata JSON column
     */
    public function removeMetadata(string $key): self
    {
        $metadata = $this->metadataArray();
        data_forget($metadata, $key);
        $this->writeMetadata($metadata);

        return $this;
    }

    /**
     * Merge multiple values at once into the metadata JSON column
     */
    public function mergeMetadata(array $newMetadata): self
    {
        $current = $this->metadataArray();
        $this->writeMetadata(array_merge($current, $newMetadata));

        return $this;
    }

    /**
     * Get all values from the metadata JSON column
     */
    public function getAllMetadata(): array
    {
        return $this->metadataArray();
    }

    /**
     * Clear all values in the metadata JSON column
     */
    public function clearMetadata(): self
    {
        $this->writeMetadata([]);

        return $this;
    }

    /**
     * Set multiple values at once in the metadata JSON column (replaces existing)
     */
    public function setAllMetadata(array $metadata): self
    {
        $this->writeMetadata($metadata);

        return $this;
    }

    private function metadataArray(): array
    {
        $metadata = $this->getAttribute('metadata');

        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function writeMetadata(array $metadata): void
    {
        $this->setAttribute('metadata', $metadata);
    }
}
