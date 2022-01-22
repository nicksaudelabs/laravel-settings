<?php

namespace Spatie\LaravelSettings\Migrations;

use Closure;
use Illuminate\Support\Collection;
use Spatie\LaravelSettings\Exceptions\InvalidSettingName;
use Spatie\LaravelSettings\Exceptions\SettingAlreadyExists;
use Spatie\LaravelSettings\Exceptions\SettingDoesNotExist;
use Spatie\LaravelSettings\Factories\SettingsRepositoryFactory;
use Spatie\LaravelSettings\Models\SettingsProperty;
use Spatie\LaravelSettings\SettingsCasts\SettingsCast;
use Spatie\LaravelSettings\SettingsConfig;
use Spatie\LaravelSettings\SettingsContainer;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;
use Spatie\LaravelSettings\Support\Crypto;

class SettingsMigrator
{
    protected SettingsRepository $repository;

    public function __construct(SettingsRepository $connection)
    {
        $this->repository = $connection;
    }

    public function repository(string $name): self
    {
        $this->repository = SettingsRepositoryFactory::create($name);

        return $this;
    }

    public function rename(string $from, string $to): void
    {
        if (! $this->checkIfPropertyExists($from)) {
            throw SettingDoesNotExist::whenRenaming($from, $to);
        }

        if ($this->checkIfPropertyExists($to)) {
            throw SettingAlreadyExists::whenRenaming($from, $to);
        }

        $this->createProperty(
            $to,
            $this->getPropertyPayload($from)
        );

        $this->deleteProperty($from);
    }

    public function add(
        string $property,
        $value,
        string $type,
        string $label,
        int $unityId = null,
        bool $encrypted = false,
        array $options = null,
        bool $isUnique = false
    ): void
    {
        if ($this->checkIfPropertyExists($property, $unityId)) {
            throw SettingAlreadyExists::whenAdding($property);
        }

        if ($encrypted) {
            $value = Crypto::encrypt($value);
        }

        $this->createProperty($property, $value, $type, $label, $unityId, $options, $isUnique, $encrypted);
    }

    public function delete(string $property, int $unityId = null): void
    {
        if (! $this->checkIfPropertyExists($property, $unityId)) {
            throw SettingDoesNotExist::whenDeleting($property);
        }

        $this->deleteProperty($property, $unityId);
    }

    public function update(
        string $property,
        Closure $closure,
        bool $encrypted = false,
        int $unityId = null
    ): void
    {
        if (! $this->checkIfPropertyExists($property, $unityId)) {
            throw SettingDoesNotExist::whenEditing($property);
        }

        $originalPayload = $encrypted
            ? Crypto::decrypt($this->getPropertyPayload($property))
            : $this->getPropertyPayload($property);

        $updatedPayload = $encrypted
            ? Crypto::encrypt($closure($originalPayload))
            : $closure($originalPayload);

        $this->updatePropertyPayload($property, $updatedPayload, $unityId, $encrypted);
    }

    public function addEncrypted(
        string $property,
        $value,
        string $type,
        string $label,
        int $unityId = null,
        array $options = null,
        bool $isUnique = false
    ): void
    {
        $this->add($property, $value, $type, $label, $unityId, true, $options, $isUnique);
    }

    public function updateEncrypted(
        string $property,
        Closure $closure,
        int $unityId = null
    ): void
    {
        $this->update($property, $closure, true, $unityId);
    }

    public function encrypt(string $property, int $unityId = null): void
    {
        $this->update($property, fn($payload) => Crypto::encrypt($payload), false, $unityId);
    }

    public function decrypt(string $property, int $unityId = null): void
    {
        $this->update($property, fn($payload) => Crypto::decrypt($payload), false, $unityId);
    }

    public function inGroup(string $group, Closure $closure): void
    {
        $closure(new SettingsBlueprint($group, $this));
    }

    protected function getPropertyParts(string $property): array
    {
        $propertyParts = explode('.', $property);

        if (count($propertyParts) !== 2) {
            throw InvalidSettingName::create($property);
        }

        return ['group' => $propertyParts[0], 'name' => $propertyParts[1]];
    }

    protected function checkIfPropertyExists(string $property, int $unityId = null): bool
    {
        ['group' => $group, 'name' => $name] = $this->getPropertyParts($property);

        return $this->repository->checkIfPropertyExists($group, $name, $unityId);
    }

    protected function getPropertyPayload(string $property, int $unityId = null)
    {
        ['group' => $group, 'name' => $name] = $this->getPropertyParts($property);

        return $this->repository->getPropertyPayload($group, $name, $unityId);
    }

    protected function createProperty(
        string $property,
        $payload,
        string $type,
        string $label,
        int $unityId = null,
        array $options = null,
        bool $isUnique = false,
        bool $encrypted = false
    ): void
    {
        ['group' => $group, 'name' => $name] = $this->getPropertyParts($property);

        $this->repository->createProperty(
            $group,
            $name,
            $payload,
            $type,
            $label,
            $unityId,
            $options,
            $isUnique,
            $encrypted
        );
    }

    protected function updatePropertyPayload(
        string $property,
        $payload,
        int $unityId = null,
        bool $encrypted = false
    ): void
    {
        ['group' => $group, 'name' => $name] = $this->getPropertyParts($property);

        $this->repository->updatePropertyPayload($group, $name, $payload, $unityId, $encrypted);
    }

    protected function deleteProperty(string $property, int $unityId = null): void
    {
        ['group' => $group, 'name' => $name] = $this->getPropertyParts($property);

        $this->repository->deleteProperty($group, $name, $unityId);
    }

    protected function getCast(string $group, string $name): ?SettingsCast
    {
        return optional($this->settingsGroups()->get($group))->getCast($name);
    }

    protected function settingsGroups(): Collection
    {
        return app(SettingsContainer::class)
            ->getSettingClasses()
            ->mapWithKeys(fn (string $settingsClass) => [
                $settingsClass::group() => new SettingsConfig($settingsClass),
            ]);
    }
}
