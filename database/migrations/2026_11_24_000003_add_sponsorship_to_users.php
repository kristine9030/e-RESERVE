<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a sponsor availed: `sponsor_type` (individual | event) and `sponsor_tier`
 * (gold / silver / bronze, or puso / dedikasyon / talino). Null for everyone else.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('sponsor_type', 16)->nullable()->after('member_type');
            $t->string('sponsor_tier', 16)->nullable()->after('sponsor_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['sponsor_type', 'sponsor_tier']);
        });
    }
};
