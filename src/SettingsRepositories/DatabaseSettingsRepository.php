<?php

namespace Spatie\LaravelSettings\SettingsRepositories;

use App\Models\UnityModel;
use Illuminate\Database\Eloquent\Builder;
use Spatie\LaravelSettings\Models\SettingsProperty;

class DatabaseSettingsRepository implements SettingsRepository
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    protected string $propertyModel;

    protected ?string $connection;

    protected ?string $table;

    public function __construct(array $config)
    {
        $this->propertyModel = $config['model'] ?? SettingsProperty::class;
        $this->connection = $config['connection'] ?? null;
        $this->table = $config['table'] ?? null;
    }

    public function getPropertiesInGroup(string $group): array
    {
        // It means we are editing the settings, so we don't want to
        // rely on the current set Unity.
        if (request()?->routeIs('admin.settings.*')) {
            $unityId = session()->has('settings_scope') && 'global' !== session()->get('settings_scope')
                ? (int) session()->get('settings_scope')
                : null;
        } else {
            $unityId = null !== UnityModel::current() ? UnityModel::current()->id : null;
        }

        /**
         * @var \Spatie\LaravelSettings\Models\SettingsProperty $temp
         * @psalm-suppress UndefinedClass
         */
        $temp = new $this->propertyModel;

        $settings = $this->getBuilder()
            ->where(SettingsProperty::GROUP, $group)
            ->whereNull(SettingsProperty::UNITY_ID)
            ->get([
                SettingsProperty::NAME,
                SettingsProperty::PAYLOAD,
            ]);

        if ($unityId) {
            $scoped = $this->getBuilder()
                ->where(SettingsProperty::GROUP, $group)
                ->where(SettingsProperty::UNITY_ID, '=', $unityId)
                ->get([
                    SettingsProperty::NAME,
                    SettingsProperty::PAYLOAD,
                ]);

            $settings = collect($settings)->map(function ($item) use ($scoped) {
                $found = $scoped->first(function ($property) use ($item) {
                    return $property->{SettingsProperty::NAME} === $item->{SettingsProperty::NAME};
                });

                if (null !== $found) {
                    $item->{SettingsProperty::PAYLOAD} = $found->{SettingsProperty::PAYLOAD};
                }

                return $item;
            });
        }

        return collect($settings)
            ->mapWithKeys(function (object $item) {
                return [$item->{SettingsProperty::NAME} => json_decode($item->{SettingsProperty::PAYLOAD}, true)];
            })
            ->toArray();
    }

    public function checkIfPropertyExists(string $group, string $name, int $unityId = null): bool
    {
        return $this->getBuilder()
            ->where(SettingsProperty::GROUP, $group)
            ->where(SettingsProperty::UNITY_ID, $unityId)
            ->where(SettingsProperty::NAME, $name)
            ->exists();
    }

    public function getPropertyPayload(string $group, string $name, int $unityId = null)
    {
        $setting = $this->getBuilder()
            ->where(SettingsProperty::GROUP, $group)
            ->where(SettingsProperty::UNITY_ID, $unityId)
            ->where(SettingsProperty::NAME, $name)
            ->first(SettingsProperty::PAYLOAD)
            ?->toArray();

        return json_decode($setting[SettingsProperty::PAYLOAD]);
    }

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
    ): void {
        $this->getBuilder()->create([
            SettingsProperty::GROUP => $group,
            SettingsProperty::NAME => $name,
            SettingsProperty::LOCKED => false,
            SettingsProperty::PAYLOAD => json_encode($payload),
            SettingsProperty::TYPE => $type,
            SettingsProperty::OPTIONS => $options,
            SettingsProperty::IS_UNIQUE => $isUnique,
            SettingsProperty::IS_ENCRYPTED => $encrypted,
            SettingsProperty::LABEL => $label,
            SettingsProperty::UNITY_ID => $unityId,
        ]);
    }

    public function updatePropertyPayload(
        string $group,
        string $name,
        $payload,
        int $unityId = null,
        bool $encrypted = false
    ): void {
        if ($unityId === null) {
            if (request()?->routeIs('admin.settings.*')) {
                $unityId = session()->has('settings_scope') && 'global' !== session()->get('settings_scope')
                    ? (int) session()->get('settings_scope')
                    : null;
            } else {
                $unityId = null !== UnityModel::current() ? UnityModel::current()->id : null;
            }
        }

        $this->getBuilder()
            ->where(SettingsProperty::GROUP, $group)
            ->where(SettingsProperty::NAME, $name)
            ->where(SettingsProperty::UNITY_ID, $unityId)
            ->update([
                SettingsProperty::PAYLOAD => json_encode($payload),
                SettingsProperty::IS_ENCRYPTED => $encrypted,
            ]);
    }

    public function deleteProperty(string $group, string $name, int $unityId = null): void
    {
        $this->getBuilder()
            ->where(SettingsProperty::GROUP, $group)
            ->where(SettingsProperty::NAME, $name)
            ->where(SettingsProperty::UNITY_ID, $unityId)
            ->delete();
    }

    public function lockProperties(string $group, array $properties, int $unityId = null): void
    {
        $this->getBuilder()
            ->where(SettingsProperty::GROUP, $group)
            ->where(SettingsProperty::UNITY_ID, $unityId)
            ->whereIn(SettingsProperty::NAME, $properties)
            ->update([SettingsProperty::LOCKED => true]);
    }

    public function unlockProperties(string $group, array $properties, int $unityId = null): void
    {
        $this->getBuilder()
            ->where(SettingsProperty::GROUP, $group)
            ->where(SettingsProperty::UNITY_ID, $unityId)
            ->whereIn(SettingsProperty::NAME, $properties)
            ->update([SettingsProperty::LOCKED => false]);
    }

    public function getLockedProperties(string $group, int $unityId = null): array
    {
        return $this->getBuilder()
            ->where(SettingsProperty::GROUP, $group)
            ->where(SettingsProperty::UNITY_ID, $unityId)
            ->where(SettingsProperty::LOCKED, true)
            ->pluck(SettingsProperty::NAME)
            ->toArray();
    }

    public function getBuilder(): Builder
    {
        $model = new $this->propertyModel;

        if ($this->connection) {
            $model->setConnection($this->connection);
        }

        if ($this->table) {
            $model->setTable($this->table);
        }

        return $model->newQuery();
    }
}
