<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Generic key-value store for admin-controlled feature toggles (e.g. showing
 * advanced/testing-only controls in end-user forms). Deliberately a flat
 * key/value table rather than one column per setting, so adding a new
 * toggle later never needs a migration.
 */
class Setting extends Model
{
    protected $table = 'app_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        // A feature-flag lookup should never be able to take a page down --
        // falls back to $default if app_settings is unreachable/missing
        // (e.g. a fresh environment before this migration has run) rather
        // than surfacing a DB error to every page that checks a toggle.
        try {
            $raw = static::query()->find($key)?->value;
        } catch (\Throwable) {
            return $default;
        }

        if ($raw === null) {
            return $default;
        }

        return is_bool($default) ? $raw === '1' : $raw;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
        );
    }
}
