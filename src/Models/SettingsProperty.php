<?php

namespace Spatie\LaravelSettings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;

/**
 * SettingsProperty Model.
 *
 * @property string      $settings
 * @property string      $uuid
 * @property string      $unity_id
 * @property string      $group
 * @property string      $name
 * @property bool        $locked
 * @property string      $payload
 * @property string      $type
 * @property array|null  $options
 * @property bool        $is_unique
 * @property bool        $is_encrypted
 * @property string      $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder globalsOnly()
 * @method static Builder scopedOnly(int $unityId)
 */
class SettingsProperty extends Model
{
    public const TABLE_NAME = 'settings';
    public const UUID = 'uuid';
    public const UNITY_ID = 'unity_id';
    public const GROUP = 'group';
    public const NAME = 'name';
    public const LOCKED = 'locked';
    public const PAYLOAD = 'payload';
    public const TYPE = 'type';
    public const OPTIONS = 'options';
    public const IS_UNIQUE = 'is_unique';
    public const IS_ENCRYPTED = 'is_encrypted';
    public const LABEL = 'label';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const DELETED_AT = 'deleted_at';

    protected $table = self::TABLE_NAME;

    protected $guarded = [];

    protected $casts = [
        self::LOCKED => 'boolean',
        self::OPTIONS => 'array',
        self::IS_UNIQUE => 'boolean',
        self::IS_ENCRYPTED => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            $uuid = Uuid::uuid6();

            if (isset($model->attributes[self::UUID]) && ! empty(trim($model->attributes[self::UUID]))) {
                try {
                    $uuid = Uuid::fromString(strtolower($model->attributes[self::UUID]));
                } catch (InvalidUuidStringException $exception) {
                    $uuid = Uuid::fromBytes($model->attributes[self::UUID]);
                }
            }

            $model->uuid = $uuid->toString();
        });
    }

    /**
     * It will get the setting entry by its name and, optionally, unity ID.
     *
     * @param string   $property
     * @param int|null $unityId
     *
     * @return mixed
     */
    public static function get(string $property, int $unityId = null)
    {
        [$group, $name] = explode('.', $property);

        $setting = self::query()
            ->where(self::GROUP, $group)
            ->where(self::NAME, $name)
            ->where(self::UNITY_ID, $unityId)
            ->first(self::PAYLOAD);

        return json_decode($setting->getAttribute(self::PAYLOAD));
    }

    public function getValue()
    {
        $group = Str::studly($this->group);
        $config = app("App\\Settings\\{$group}Settings");

        return $config->{$this->name};
    }

    /**
     * Condition to get only the global properties.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeGlobalsOnly(Builder $query)
    {
        return $query->whereNull(self::UNITY_ID);
    }

    /**
     * Condition to get only the properties specific to a unity.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeScopedOnly(Builder $query, int $unityId)
    {
        return $query->whereNull(self::UNITY_ID);
    }
}
