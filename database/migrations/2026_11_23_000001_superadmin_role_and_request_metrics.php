<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A `superadmin` role (system owner), and one row per web request so the Performance tab has real numbers. */
return new class extends Migration {
    public function up(): void
    {
        // MySQL databases created by the older migration have role as an ENUM; widen it. A plain string column needs nothing.
        if (DB::getDriverName() === 'mysql') {
            $type = (string) (DB::selectOne("SHOW COLUMNS FROM users LIKE 'role'")->Type ?? '');
            if (str_starts_with(strtolower($type), 'enum')) {
                DB::statement("ALTER TABLE users MODIFY role ENUM('student','admin','scanner','superadmin') NOT NULL DEFAULT 'student'");
            }
        }

        Schema::create('request_metrics', function (Blueprint $t) {
            $t->id();
            $t->string('route', 120);
            $t->string('method', 8);
            $t->unsignedSmallInteger('status');
            $t->unsignedInteger('duration_ms');
            $t->dateTime('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_metrics');
    }
};
