<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student / faculty / sponsor account can also hold SSC access (one email, one password).
 * `role` stays `student`; this flag lets the person switch into the SSC panel.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('ssc_access')->default(false)->after('member_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('ssc_access');
        });
    }
};
