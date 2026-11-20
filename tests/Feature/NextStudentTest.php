<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NextStudentTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    /** A student whose three files were uploaded at the given times (all waiting for review). */
    private function waiting(array $times): int
    {
        $n = ++$this->n;
        $u = User::create(['email' => "n$n@g.batstate-u.edu.ph", 'name' => "Next Student$n", 'password' => 'Test-pass-123']);
        $u->forceFill(['role' => 'student', 'status' => 'active', 'email_verified_at' => now(), 'student_no' => sprintf('26-%05d', $n), 'member_type' => 'student'])->save();
        foreach (['waiver', 'cor', 'id'] as $i => $type) {
            DB::table('documents')->insert(['user_id' => $u->id, 'doc_type' => $type, 'original_name' => 'a', 'stored_name' => uniqid('f', true),
                'size_bytes' => 1, 'status' => 'submitted', 'uploaded_at' => $times[$i] ?? end($times)]);
        }

        return $u->id;
    }

    private function admin(): User
    {
        $a = User::create(['email' => 'rev@g.batstate-u.edu.ph', 'name' => 'Reviewer', 'password' => 'Test-pass-123']);
        $a->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();

        return $a;
    }

    private function next(User $admin, int $id): ?int
    {
        return $this->actingAs($admin)->get(route('admin.documents.student', $id))->assertOk()->viewData('next');
    }

    public function test_after_approving_next_is_the_one_behind_not_the_students_that_were_skipped(): void
    {
        $admin   = $this->admin();
        $skipped = $this->waiting(['2026-11-15 08:00:00']);                                              // skipped, still to return
        $b       = $this->waiting(['2026-11-16 08:00:00', '2026-11-16 08:00:00', '2026-11-20 09:00:00']); // one file re-uploaded much later
        $c       = $this->waiting(['2026-11-17 08:00:00']);
        $d       = $this->waiting(['2026-11-18 08:00:00']);

        $this->assertSame($c, $this->next($admin, $b), 'while b is waiting, next is the one behind');

        DB::table('documents')->where('user_id', $b)->update(['status' => 'approved']);   // approve, then the page reloads
        $this->assertSame($c, $this->next($admin, $b), 'after approving b, next is still c (not d, not the skipped one)');

        DB::table('documents')->where('user_id', $c)->update(['status' => 'approved']);
        $this->assertSame($d, $this->next($admin, $c));
    }

    public function test_the_end_of_the_queue_wraps_to_the_first_one_still_waiting(): void
    {
        $admin   = $this->admin();
        $skipped = $this->waiting(['2026-11-15 08:00:00']);
        $last    = $this->waiting(['2026-11-20 08:00:00']);

        $this->next($admin, $last);
        DB::table('documents')->where('user_id', $last)->update(['status' => 'approved']);

        $this->assertSame($skipped, $this->next($admin, $last), 'nobody is behind the last one, so it goes back to who is still waiting');
    }
}
