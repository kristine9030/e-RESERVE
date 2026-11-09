<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A student asks SSC to change a locked detail (SR-code or GSuite email). Nothing changes until SSC approves. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('field', 16);                       // student_no | email
            $t->string('old_value', 190)->nullable();
            $t->string('new_value', 190);
            $t->string('reason', 300);
            $t->string('status', 12)->default('pending');  // pending | approved | rejected
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->string('decision_note', 250)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['status', 'created_at']);
            $t->index(['user_id', 'field', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
