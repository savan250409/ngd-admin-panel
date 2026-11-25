<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return Cache::rememberForever("setting.$key", fn () => static::find($key)?->value) ?? $default;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.$key");
    }

    /** The bearer token every API call must send. Created on first use. */
    public static function apiToken(): string
    {
        return static::getValue('api_token') ?: static::regenerateApiToken();
    }

    public static function regenerateApiToken(): string
    {
        $token = Str::random(64);
        static::setValue('api_token', $token);

        return $token;
    }
}
