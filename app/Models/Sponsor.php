<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'created_at' => 'datetime'];
    }

    /**
     * What the landing strip shows: the sponsors SSC added, or, while there are none,
     * the demo list in config. Shape: [name, logo path|null, icon|null, website|null].
     */
    public static function forLanding(): array
    {
        try {
            $rows = static::where('active', true)->orderBy('sort')->orderBy('id')->get();
        } catch (\Throwable) {
            $rows = collect();
        }

        if ($rows->isNotEmpty()) {
            return $rows->map(fn ($s) => [$s->name, $s->logo_path, null, $s->website])->all();
        }

        return array_map(fn ($r) => [$r[0], $r[1] ?? null, $r[2] ?? null, null], (array) config('ereserve.sponsors'));
    }
}
