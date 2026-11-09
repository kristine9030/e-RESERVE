<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - `scanner` role: gate staff who can scan tickets but not review documents.
 * - Indexes for the review queue and the staff lists, which filter on these.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL created `role` as an ENUM; widen it. (Fresh installs use a plain string column.)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('student','admin','scanner') NOT NULL DEFAULT 'student'");
        }

        Schema::table('documents', function (Blueprint $t) {
            $t->index(['status', 'uploaded_at'], 'ix_documents_queue');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->index(['role', 'created_at'], 'ix_users_role_created');
            $t->index('college_code', 'ix_users_college');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex('ix_users_college');
            $t->dropIndex('ix_users_role_created');
        });

        Schema::table('documents', function (Blueprint $t) {
            $t->dropIndex('ix_documents_queue');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE users SET role = 'admin' WHERE role = 'scanner'");
            DB::statement("ALTER TABLE users MODIFY role ENUM('student','admin') NOT NULL DEFAULT 'student'");
        }
    }
};
