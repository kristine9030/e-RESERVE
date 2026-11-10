<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Faculty/staff upload an employee ID; sponsors upload a proof of sponsorship. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE documents MODIFY doc_type ENUM('waiver','cor','id','employee_id','sponsorship') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("DELETE FROM documents WHERE doc_type IN ('employee_id','sponsorship')");
            DB::statement("ALTER TABLE documents MODIFY doc_type ENUM('waiver','cor','id') NOT NULL");
        }
    }
};
