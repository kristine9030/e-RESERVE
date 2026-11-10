<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Switches SSC flips from the panel (what the public landing page shows). Missing row = the default in App\Models\Setting. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $t) {
            $t->string('key', 60)->primary();
            $t->string('value', 255)->nullable();
            $t->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
