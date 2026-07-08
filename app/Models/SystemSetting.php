<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'description'];

    // ─────────────────────────────────────────────
    // Ambil value setting (dengan cache 5 menit)
    // ─────────────────────────────────────────────
    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember("system_setting:{$key}", 300, function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    // ─────────────────────────────────────────────
    // Simpan/update setting + hapus cache-nya
    // ─────────────────────────────────────────────
    public static function set(string $key, string $value, ?string $description = null): void
    {
        $data = ['value' => $value];
        if ($description !== null) {
            $data['description'] = $description;
        }

        static::updateOrCreate(['key' => $key], $data);

        Cache::forget("system_setting:{$key}");
    }

    // ─────────────────────────────────────────────
    // Hapus setting (dipakai buat fitur "reset ke default")
    // ─────────────────────────────────────────────
    public static function forget(string $key): void
    {
        static::where('key', $key)->delete();
        Cache::forget("system_setting:{$key}");
    }
}
