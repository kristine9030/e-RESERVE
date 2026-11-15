<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amazon SES tells us when an email to an address could not be delivered (a permanent bounce)
 * or was reported as spam (a complaint). We remember it here so the SSC can see it, the student
 * is told, and we stop mailing an address that keeps bouncing.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('email_bounced_at')->nullable()->after('email_verified_at');
            $t->string('email_bounce_type', 16)->nullable()->after('email_bounced_at');   // bounce | complaint
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['email_bounced_at', 'email_bounce_type']);
        });
    }
};
