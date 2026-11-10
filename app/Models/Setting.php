<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SSC-controlled on/off switches for the public landing page.
 * Everything defaults to OFF: nothing is announced until the SSC says so.
 */
class Setting extends Model
{
    protected $table = 'site_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    /** key => [label, help]. Only these keys can be written. */
    public const SWITCHES = [
        'show_slots'    => ['Show the number of slots', 'The “slots available / left” bar and every public mention of how many tickets there are.'],
        'show_sponsors' => ['Publish the sponsors section', 'The “With thanks to our sponsors & partners” strip with the logos.'],
    ];

    public static function enabled(string $key): bool
    {
        try {
            return static::whereKey($key)->value('value') === '1';
        } catch (\Throwable) {
            return false;   // table not migrated yet: stay hidden
        }
    }

    public static function read(string $key, string $default = ''): string
    {
        try {
            return (string) (static::whereKey($key)->value('value') ?? $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function store(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }

    public static function put(string $key, bool $on): void
    {
        abort_unless(isset(self::SWITCHES[$key]), 422);
        static::updateOrCreate(['key' => $key], ['value' => $on ? '1' : '0', 'updated_at' => now()]);
    }
}
