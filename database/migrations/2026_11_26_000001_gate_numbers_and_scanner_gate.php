<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gates are numbered 1–5 (not A–E), and a scanner account can be pinned to one gate so
 * everything it scans is counted under that gate.
 */
return new class extends Migration
{
    private const MAP = ['Gate A' => 'Gate 1', 'Gate B' => 'Gate 2', 'Gate C' => 'Gate 3', 'Gate D' => 'Gate 4', 'Gate E' => 'Gate 5'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('gate', 32)->nullable();
        });

        foreach (self::MAP as $old => $new) {
            DB::table('checkins')->where('gate', $old)->update(['gate' => $new]);
        }
    }

    public function down(): void
    {
        foreach (array_flip(self::MAP) as $new => $old) {
            DB::table('checkins')->where('gate', $new)->update(['gate' => $old]);
        }

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('gate');
        });
    }
};
