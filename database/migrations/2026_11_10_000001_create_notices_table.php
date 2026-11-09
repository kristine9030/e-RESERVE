<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** In-app notifications shown under the bell in the student's navbar. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 24);                  // submitted | approved | returned | ticket | waitlist
            $t->string('title', 120);
            $t->string('body', 400)->nullable();
            $t->string('url', 255)->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'read_at', 'id'], 'ix_notices_user_unread');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
