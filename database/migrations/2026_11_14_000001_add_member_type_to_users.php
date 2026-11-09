<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is registering: a student, or an employee (faculty, instructor, staff, administration).
 * Employees need no documents: a confirmed email is enough for a ticket. Their employee ID is
 * kept in users.student_no (the unique ID column), shown as "Employee ID".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('member_type', 16)->default('student')->after('role')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('member_type');
        });
    }
};
