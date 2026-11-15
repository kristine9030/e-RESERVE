<?php

use App\Support\DocumentSets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `upload_completed_at`: when the person finished uploading every file they need (set once).
 * `waitlisted_at`: they finished after their group's tickets were already full. They are told so,
 * promised nothing, and get no ticket until the SSC releases seats for them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('upload_completed_at')->nullable()->after('email_bounce_type');
            $t->timestamp('waitlisted_at')->nullable()->after('upload_completed_at');
        });

        // People who already uploaded everything before this existed count as having completed.
        DB::table('users')->where('role', 'student')->orderBy('id')->each(function ($u) {
            $need = DocumentSets::countFor($u->member_type);
            $docs = DB::table('documents')->where('user_id', $u->id);
            if ($need > 0 && (clone $docs)->count() >= $need) {
                DB::table('users')->where('id', $u->id)->update(['upload_completed_at' => (clone $docs)->max('uploaded_at')]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['upload_completed_at', 'waitlisted_at']);
        });
    }
};
