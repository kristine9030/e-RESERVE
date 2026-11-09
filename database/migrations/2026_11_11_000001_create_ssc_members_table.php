<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The SSC roster: who holds which position, and which login (if any) belongs to them. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ssc_members', function (Blueprint $t) {
            $t->id();
            $t->string('position', 80);
            $t->string('org_group', 16)->index();     // adviser | consultant | executive | governor | committee
            $t->string('committee', 16)->nullable();  // CommCAP, CommVIDTO ... (committee members only)
            $t->string('full_name', 190);              // as written in the SSC directory
            $t->string('display_name', 190);           // "Given Middle Surname", used for the account
            $t->string('email', 190)->nullable()->unique();   // their @g.batstate-u.edu.ph address
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssc_members');
    }
};
