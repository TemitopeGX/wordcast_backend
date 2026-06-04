<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSettings extends Model
{
    protected $table = 'platform_settings';

    protected $fillable = ['key', 'value', 'group', 'type'];

    /**
     * Get a setting value by key, cast to the appropriate type.
     */
    public static function get(string $key, $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) return $default;

        return match ($setting->type) {
            'boolean'   => (bool) $setting->value,
            'integer'   => (int) $setting->value,
            'json'      => json_decode($setting->value, true),
            'encrypted' => self::safeDecrypt($setting->value),
            default     => $setting->value,
        };
    }

    /**
     * Set (upsert) a setting value.
     */
    public static function set(string $key, $value, string $group = 'general', string $type = 'string'): void
    {
        if ($type === 'encrypted') {
            $value = encrypt((string) $value);
        } elseif ($type === 'json') {
            $value = json_encode($value);
        } elseif ($type === 'boolean') {
            $value = $value ? '1' : '0';
        } else {
            $value = (string) ($value ?? '');
        }

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group, 'type' => $type]
        );
    }

    /**
     * Get all settings for a group as a flat key=>value array.
     */
    public static function getGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(fn($s) => [$s->key => static::get($s->key)])
            ->toArray();
    }

    /**
     * Return masked value showing only last N characters.
     */
    public static function masked(string $key, int $show = 4): string
    {
        try {
            $val = static::get($key);
            if (!$val) return '';
            $len = strlen($val);
            if ($len <= $show) return str_repeat('*', $len);
            return str_repeat('*', max(4, $len - $show)) . substr($val, -$show);
        } catch (\Throwable) {
            return '****';
        }
    }

    private static function safeDecrypt(string $value): string
    {
        try {
            return decrypt($value);
        } catch (\Throwable) {
            return '';
        }
    }
}
