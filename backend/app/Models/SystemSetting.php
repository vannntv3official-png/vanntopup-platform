<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'group', 'is_public', 'updated_by_user_id'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->find($key);

        return $setting ? ($setting->value['v'] ?? $setting->value) : $default;
    }

    public static function setValue(string $key, mixed $value, string $group = 'general', bool $isPublic = false, ?string $updatedBy = null): static
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => ['v' => $value],
                'group' => $group,
                'is_public' => $isPublic,
                'updated_by_user_id' => $updatedBy,
            ],
        );
    }
}
