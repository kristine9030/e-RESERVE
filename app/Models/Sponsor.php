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

    /** What the landing strip shows: the active sponsors SSC added. Shape: [name, logo path|null, icon|null, website|null]. */
    public static function forLanding(): array
    {
        try {
            return static::where('active', true)->orderBy('sort')->orderBy('id')->get()
                ->map(fn ($s) => [$s->name, $s->logo_path, null, $s->website])->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
