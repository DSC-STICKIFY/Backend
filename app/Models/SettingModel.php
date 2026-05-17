<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SettingModel extends Model
{
    protected $table = 'settings';
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key, with optional default.
     * Uses cache to avoid repeated DB queries.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 300, function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    /**
     * Set a setting value, clears cache automatically.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key'   => $key],
            ['value' => $value]
        );

        // Clear cache so next read picks up the new value
        Cache::forget("setting:{$key}");
    }
}