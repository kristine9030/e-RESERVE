<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sponsors shown on the landing page (managed by SSC), and enquiries from companies who want to sponsor. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('logo_path', 190)->nullable();     // under public/, e.g. uploads/sponsors/abc.png
            $t->string('website', 190)->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('sponsor_inquiries', function (Blueprint $t) {
            $t->id();
            $t->string('company', 120);
            $t->string('contact_name', 120);
            $t->string('email', 190);
            $t->string('phone', 32)->nullable();
            $t->string('message', 1000)->nullable();
            $t->string('status', 12)->default('new');     // new | contacted | closed
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_inquiries');
        Schema::dropIfExists('sponsors');
    }
};
