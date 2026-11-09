<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * e-RESERVE schema: accounts, the registrar masterlist, the three documents,
 * tickets, gate check-ins, the audit trail and the ticket-number sequence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('email', 190)->unique();
            $t->string('password')->nullable();               // null = Google-only account
            $t->string('role', 16)->default('student')->index();     // student | admin | scanner (MySQL: see 2026_11_09 migration)
            $t->string('name', 160);
            $t->string('initials', 4)->default('');
            $t->string('student_no', 32)->nullable()->unique();
            $t->string('college_code', 16)->nullable();
            $t->string('program', 160)->nullable();
            $t->string('year_level', 32)->nullable();
            $t->string('mobile', 32)->nullable();
            $t->enum('status', ['active', 'disabled'])->default('active');

            // Email confirmation: only the SHA-256 of the token is stored.
            $t->dateTime('email_verified_at')->nullable();
            $t->char('verify_token_hash', 64)->nullable()->index();
            $t->dateTime('verify_expires_at')->nullable();
            $t->dateTime('verify_sent_at')->nullable();

            $t->dateTime('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        // The registrar's masterlist — sign-up is checked against it.
        Schema::create('enrolled_students', function (Blueprint $t) {
            $t->string('sr_code', 10)->primary();
            $t->string('full_name', 160)->index();
            $t->string('college', 16)->index();
            $t->string('program', 160);
            $t->string('major', 120)->nullable();
            $t->string('year_level', 16);
        });

        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('doc_type', ['waiver', 'cor', 'id']);
            $t->string('original_name', 190);
            $t->string('stored_name', 128)->unique();
            $t->unsignedInteger('size_bytes');
            $t->enum('status', ['submitted', 'approved', 'rejected'])->default('submitted')->index();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('reviewed_at')->nullable();
            $t->string('review_note', 255)->nullable();
            $t->string('uploaded_ip', 45)->nullable();
            $t->dateTime('uploaded_at')->useCurrent();
            $t->unique(['user_id', 'doc_type'], 'uq_documents_user_type');
        });

        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('ticket_no', 32)->unique();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->char('verify_hash', 64);
            $t->enum('status', ['valid', 'used', 'void'])->default('valid')->index();
            $t->dateTime('issued_at')->useCurrent();
            $t->dateTime('voided_at')->nullable();
        });

        Schema::create('checkins', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();   // one scan per ticket
            $t->string('gate', 32);
            $t->string('scanner', 64);
            $t->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('scanned_at')->useCurrent();
        });

        Schema::create('audit_log', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('actor_name', 160);
            $t->string('actor_role', 32);
            $t->string('action', 64);
            $t->string('entity_type', 32);
            $t->string('entity_id', 64)->nullable();
            $t->json('context')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->dateTime('created_at')->useCurrent()->index();
            $t->index(['entity_type', 'entity_id'], 'ix_audit_entity');
        });

        Schema::create('sequences', function (Blueprint $t) {
            $t->string('seq_key', 32)->primary();
            $t->unsignedInteger('current_value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('checkins');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('enrolled_students');
        Schema::dropIfExists('users');
    }
};
