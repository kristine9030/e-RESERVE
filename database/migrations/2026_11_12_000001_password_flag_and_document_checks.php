<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // An account made (or reset) by the SSC must pick its own password on first sign-in.
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('must_change_password')->default(false)->after('password');
        });

        // Automatic checks run on every upload; staff see them beside the file.
        Schema::table('documents', function (Blueprint $t) {
            $t->char('file_hash', 64)->nullable()->after('size_bytes');
            $t->json('auto_checks')->nullable()->after('file_hash');
            $t->index('file_hash', 'ix_documents_hash');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->dropIndex('ix_documents_hash');
            $t->dropColumn(['file_hash', 'auto_checks']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('must_change_password');
        });
    }
};
