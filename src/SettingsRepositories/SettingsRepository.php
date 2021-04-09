<?php

namespace Spatie\LaravelSettings\SettingsRepositories;

interface SettingsRepository
{
    /**
     * Get all the properties in the repository for a single group
     */
    public function getPropertiesInGroup(string $group): array;

    /**
     * Check if a property exists in a group
     */
    public function checkIfPropertyExists(string $group, string $name, int $unityId = null): bool;

    /**
     * Get the payload of a property
     */
    public function getPropertyPayload(string $group, string $name, int $unityId = null);

    /**
     * Create a property within a group with a payload
     */
    public function createProperty(
        string $group,
        string $name,
        $payload,
        string $type,
        string $label,
        int $unityId = null,
        array $options = null,
        bool $isUnique = false,
        bool $encrypted = false
    ): void;

    /**
     * Update the payload of a property within a group
     */
    public function updatePropertyPayload(
        string $group,
        string $name,
        $payload,
        int $unityId = null,
        bool $encrypted = false
    ): void;

    /**
     * Delete a property from a group
     */
    public function deleteProperty(string $group, string $name, int $unityId = null): void;

    /**
     * Lock a set of properties for a specific group
     */
    public function lockProperties(string $group, array $properties, int $unityId = null): void;

    /**
     * Unlock a set of properties for a group
     */
    public function unlockProperties(string $group, array $properties, int $unityId = null): void;

    /**
     * Get all the locked properties within a group
     */
    public function getLockedProperties(string $group, int $unityId = null): array;
}
