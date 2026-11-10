<?php

namespace App\Services;

use App\Support\DocumentSets;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Read-only queries behind the staff dashboards and the reports. */
class Stats
{
    private static function rows(string $sql, array $b = []): array
    {
        return TicketService::rows($sql, $b);
    }

    /** Every student with the state of their three documents and ticket, newest first. */
    public function registrations(?int $limit = null): array
    {
        $rows = self::rows(
            'SELECT u.id, u.name, u.email, u.student_no, u.college_code, u.program, u.year_level, u.created_at, u.email_verified_at, u.status AS account_status, u.member_type,
                    MAX(CASE WHEN d.doc_type = \'waiver\' THEN d.status END) AS waiver_status,
                    MAX(CASE WHEN d.doc_type = \'cor\'    THEN d.status END) AS cor_status,
                    MAX(CASE WHEN d.doc_type = \'id\'     THEN d.status END) AS id_status,
                    MAX(CASE WHEN d.doc_type = \'waiver\' THEN d.id END)     AS waiver_id,
                    MAX(CASE WHEN d.doc_type = \'cor\'    THEN d.id END)     AS cor_id,
                    MAX(CASE WHEN d.doc_type = \'id\'     THEN d.id END)     AS id_id,
                    MAX(CASE WHEN d.doc_type = \'employee_id\' THEN d.status END) AS employee_id_status,
                    MAX(CASE WHEN d.doc_type = \'employee_id\' THEN d.id END)     AS employee_id_id,
                    MAX(CASE WHEN d.doc_type = \'sponsorship\' THEN d.status END) AS sponsorship_status,
                    MAX(CASE WHEN d.doc_type = \'sponsorship\' THEN d.id END)     AS sponsorship_id,
                    COUNT(d.id) AS doc_count,
                    t.ticket_no, t.status AS ticket_status, t.issued_at,
                    c.scanned_at
             FROM users u
             LEFT JOIN documents d ON d.user_id = u.id
             LEFT JOIN tickets t   ON t.user_id = u.id
             LEFT JOIN checkins c  ON c.ticket_id = t.id
             WHERE u.role = "student"
             GROUP BY u.id, u.name, u.email, u.student_no, u.college_code, u.program, u.year_level, u.created_at,
                      u.email_verified_at, u.status, u.member_type, t.id, t.ticket_no, t.status, t.issued_at, c.id, c.scanned_at
             ORDER BY u.created_at DESC, u.id DESC' . ($limit ? ' LIMIT ' . max(1, $limit) : '')
        );

        return array_map(static fn(array $r): array => [
            'id'            => (int) $r['id'],
            'name'          => $r['name'],
            'email'         => $r['email'],
            'student_no'    => $r['student_no'],
            'college'       => $r['college_code'],
            'program'       => $r['program'],
            'year'          => $r['year_level'],
            'registered_at' => $r['created_at'],
            'verified'      => $r['email_verified_at'] !== null,
            'account_status' => $r['account_status'],
            'member_type'   => $r['member_type'] ?: 'student',
            'docs'          => (static function (array $r): array {
                $mine = DocumentSets::keysFor($r['member_type']);
                $out  = [];
                foreach (['waiver', 'cor', 'id', 'employee_id', 'sponsorship'] as $k) {
                    $out[$k] = ['status' => $r[$k . '_status'], 'id' => $r[$k . '_id'] ? (int) $r[$k . '_id'] : null, 'applicable' => in_array($k, $mine, true)];
                }

                return $out;
            })($r),
            'doc_total'     => DocumentSets::countFor($r['member_type']),
            'doc_count'     => (int) $r['doc_count'],
            'ticket_no'     => $r['ticket_no'],
            'ticket_status' => $r['ticket_status'],
            'issued_at'     => $r['issued_at'],
            'checked_in'    => !empty($r['scanned_at']),
            'checked_at'    => $r['scanned_at'],
        ], $rows);
    }

    /**
     * One page of students for the staff list, filtered in SQL so the page stays
     * fast with thousands of accounts. Same row shape as registrations().
     *
     * @param array{q?:string,college?:string,docs?:string,ticket?:string,program?:string,type?:string} $f
     */
    public function registrationsPage(array $f, int $perPage = 25): LengthAwarePaginator
    {
        $need  = DocumentSets::sqlRequired('u.member_type');
        $q     = DB::table('users as u')->where('u.role', 'student');

        if (($term = trim((string) ($f['q'] ?? ''))) !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $q->where(fn ($w) => $w->where('u.name', 'like', $like)->orWhere('u.email', 'like', $like)->orWhere('u.student_no', 'like', $like));
        }
        if (($c = (string) ($f['college'] ?? '')) !== '' && array_key_exists($c, config('ereserve.colleges'))) {
            $q->where('u.college_code', $c);
        }

        $has = static fn (string $status) => 'EXISTS (SELECT 1 FROM documents d WHERE d.user_id = u.id AND d.status = "' . $status . '")';
        match ((string) ($f['docs'] ?? '')) {
            'pending'    => $q->whereRaw($has('submitted')),
            'returned'   => $q->whereRaw($has('rejected')),
            'approved'   => $q->whereRaw('(SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id AND d.status = "approved") = ' . $need),
            'incomplete' => $q->whereRaw('(SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id) < ' . $need),
            default      => null,
        };
        $type = (string) ($f['type'] ?? '');
        match (true) {
            $type === 'student'  => $q->where(fn ($w) => $w->whereNull('u.member_type')->orWhere('u.member_type', 'student')),
            $type === 'sponsor'  => $q->where('u.member_type', 'sponsor'),
            $type === 'employee' => $q->whereNotNull('u.member_type')->whereNotIn('u.member_type', ['student', 'sponsor']),
            array_key_exists($type, config('ereserve.employee_types')) => $q->where('u.member_type', $type),
            default              => null,
        };
        if (($pg = trim((string) ($f['program'] ?? ''))) !== '') {
            $q->where('u.program', $pg);
        }
        match ((string) ($f['ticket'] ?? '')) {
            'held'    => $q->whereRaw('EXISTS (SELECT 1 FROM tickets t WHERE t.user_id = u.id AND t.status <> "void")'),
            'issued'  => $q->whereRaw('EXISTS (SELECT 1 FROM tickets t WHERE t.user_id = u.id AND t.status = "valid")'),
            'used'    => $q->whereRaw('EXISTS (SELECT 1 FROM tickets t WHERE t.user_id = u.id AND t.status = "used")'),
            'none'    => $q->whereRaw('NOT EXISTS (SELECT 1 FROM tickets t WHERE t.user_id = u.id AND t.status <> "void")'),
            default   => null,
        };

        $page = $q->orderByDesc('u.created_at')->orderByDesc('u.id')
            ->select('u.id', 'u.name', 'u.email', 'u.student_no', 'u.college_code', 'u.program', 'u.year_level',
                     'u.created_at', 'u.email_verified_at', 'u.status as account_status', 'u.member_type')
            ->paginate($perPage)->withQueryString();

        $ids  = $page->pluck('id')->all();
        $docs = DB::table('documents')->whereIn('user_id', $ids)->get()->groupBy('user_id');
        $tix  = DB::table('tickets as t')->leftJoin('checkins as c', 'c.ticket_id', '=', 't.id')
            ->whereIn('t.user_id', $ids)->get(['t.user_id', 't.ticket_no', 't.status', 't.issued_at', 'c.scanned_at'])->keyBy('user_id');

        return $page->through(function ($u) use ($docs, $tix): array {
            $mine = ($docs[$u->id] ?? collect())->keyBy('doc_type');
            $t    = $tix[$u->id] ?? null;
            $d    = static fn (string $type): array => [
                'status' => $mine[$type]->status ?? null,
                'id'     => isset($mine[$type]) ? (int) $mine[$type]->id : null,
            ];

            return [
                'id' => (int) $u->id, 'name' => $u->name, 'email' => $u->email, 'student_no' => $u->student_no,
                'college' => $u->college_code, 'program' => $u->program, 'year' => $u->year_level,
                'member_type' => $u->member_type ?? 'student',
                'registered_at' => $u->created_at, 'verified' => $u->email_verified_at !== null,
                'account_status' => $u->account_status,
                'docs' => collect(['waiver', 'cor', 'id', 'employee_id', 'sponsorship'])->mapWithKeys(
                    fn (string $k) => [$k => $d($k) + ['applicable' => in_array($k, DocumentSets::keysFor($u->member_type), true)]]
                )->all(),
                'doc_total' => DocumentSets::countFor($u->member_type),
                'doc_count' => $mine->count(),
                'ticket_no' => $t->ticket_no ?? null, 'ticket_status' => $t->status ?? null, 'issued_at' => $t->issued_at ?? null,
                'checked_in' => !empty($t->scanned_at), 'checked_at' => $t->scanned_at ?? null,
            ];
        });
    }

    /** One page of the review queue (oldest first), optionally filtered by student or document type. */
    public function pendingPage(array $f, int $perPage = 20): LengthAwarePaginator
    {
        $q = DB::table('documents as d')->join('users as u', 'u.id', '=', 'd.user_id')->where('d.status', 'submitted');

        if (($term = trim((string) ($f['q'] ?? ''))) !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $q->where(fn ($w) => $w->where('u.name', 'like', $like)->orWhere('u.student_no', 'like', $like));
        }
        if (($t = (string) ($f['type'] ?? '')) !== '' && array_key_exists($t, config('ereserve.documents'))) {
            $q->where('d.doc_type', $t);
        }

        return $q->orderBy('d.uploaded_at')->orderBy('d.id')
            ->select('d.*', 'u.name as student_name', 'u.student_no', 'u.program', 'u.year_level')
            ->paginate($perPage)->withQueryString()
            ->through(fn ($r): array => (array) $r);
    }

    /**
     * One page of the review queue, one row per STUDENT who has at least one
     * document waiting (longest-waiting first), each with their three documents.
     *
     * @param array{q?:string,college?:string} $f
     */
    public function pendingStudentsPage(array $f, int $perPage = 20): LengthAwarePaginator
    {
        $q = DB::table('users as u')->where('u.role', 'student')
            ->whereRaw('EXISTS (SELECT 1 FROM documents d WHERE d.user_id = u.id AND d.status = "submitted")');

        if (($term = trim((string) ($f['q'] ?? ''))) !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $q->where(fn ($w) => $w->where('u.name', 'like', $like)->orWhere('u.student_no', 'like', $like)->orWhere('u.email', 'like', $like));
        }
        if (($c = (string) ($f['college'] ?? '')) !== '' && array_key_exists($c, config('ereserve.colleges'))) {
            $q->where('u.college_code', $c);
        }
        $this->applyMemberGroup($q, (string) ($f['type'] ?? ''));

        $page = $q->select('u.id', 'u.name', 'u.email', 'u.student_no', 'u.college_code', 'u.program', 'u.year_level', 'u.member_type')
            ->selectSub('SELECT MIN(d.uploaded_at) FROM documents d WHERE d.user_id = u.id AND d.status = "submitted"', 'waiting_since')
            ->orderBy('waiting_since')->orderBy('u.id')
            ->paginate($perPage)->withQueryString();

        $docs = DB::table('documents')->whereIn('user_id', $page->pluck('id')->all())->get()->groupBy('user_id');

        return $page->through(function ($u) use ($docs): array {
            $mine = ($docs[$u->id] ?? collect())->keyBy('doc_type');
            $row  = (array) $u;
            $row['docs'] = [];
            foreach (DocumentSets::keysFor($u->member_type) as $type) {
                $row['docs'][$type] = isset($mine[$type]) ? (array) $mine[$type] : null;
            }
            $row['waiting'] = $mine->where('status', 'submitted')->count();

            return $row;
        });
    }

    /** Limit a query on users (alias u) to one tab: student, employee (faculty/instructor/staff) or sponsor. */
    private function applyMemberGroup($q, string $group): void
    {
        $employee = array_keys(config('ereserve.employee_types'));
        match ($group) {
            'student'  => $q->where(fn ($w) => $w->whereNull('u.member_type')->orWhere('u.member_type', 'student')),
            'employee' => $q->whereIn('u.member_type', $employee),
            'sponsor'  => $q->where('u.member_type', 'sponsor'),
            default    => null,
        };
    }

    /** How many people are waiting in each review tab (ignores the tab itself, respects search and college). */
    public function pendingGroupCounts(array $f): array
    {
        $out = [];
        foreach (['all', 'student', 'employee', 'sponsor'] as $g) {
            $q = DB::table('users as u')->where('u.role', 'student')
                ->whereRaw('EXISTS (SELECT 1 FROM documents d WHERE d.user_id = u.id AND d.status = "submitted")');
            if (($term = trim((string) ($f['q'] ?? ''))) !== '') {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('u.name', 'like', $like)->orWhere('u.student_no', 'like', $like)->orWhere('u.email', 'like', $like));
            }
            if (($c = (string) ($f['college'] ?? '')) !== '' && array_key_exists($c, config('ereserve.colleges'))) {
                $q->where('u.college_code', $c);
            }
            $this->applyMemberGroup($q, $g);
            $out[$g] = $q->count();
        }

        return $out;
    }

    /** A student with their three documents (null where not uploaded yet), for the review page. */
    public function studentForReview(int $userId): ?array
    {
        $u = DB::table('users')->where('id', $userId)->where('role', 'student')->first();
        if (!$u) {
            return null;
        }

        $mine = DB::table('documents as d')->leftJoin('users as v', 'v.id', '=', 'd.reviewed_by')
            ->where('d.user_id', $userId)->get(['d.*', 'v.name as reviewer'])->keyBy('doc_type');
        $docs = [];
        foreach (DocumentSets::keysFor($u->member_type) as $type) {
            $docs[$type] = isset($mine[$type]) ? (array) $mine[$type] : null;
        }
        $ticket = DB::table('tickets')->where('user_id', $userId)->first();

        return ['user' => (array) $u, 'docs' => $docs, 'ticket' => $ticket ? (array) $ticket : null];
    }

    /** The student who has waited longest, other than $exceptId (for "next student"). */
    public function nextWaitingStudent(int $exceptId, ?string $college = null): ?int
    {
        $q = DB::table('users as u')->where('u.role', 'student')->where('u.id', '<>', $exceptId)
            ->whereRaw('EXISTS (SELECT 1 FROM documents d WHERE d.user_id = u.id AND d.status = "submitted")');
        if ($college && array_key_exists($college, config('ereserve.colleges'))) {
            $q->where('u.college_code', $college);
        }

        $id = $q->selectSub('SELECT MIN(d.uploaded_at) FROM documents d WHERE d.user_id = u.id AND d.status = "submitted"', 'ws')
            ->orderBy('ws')->orderBy('u.id')->value('u.id');

        return $id ? (int) $id : null;
    }

    /**
     * Numbers per college for the analytics cards: registered students, how many
     * are waiting on SSC, were sent back, are fully approved, and hold a ticket.
     *
     * @return array<string,array{code:string,name:string,students:int,waiting:int,docs_waiting:int,returned:int,approved:int,tickets:int,share:float,done:float}>
     */
    public function collegeBreakdown(): array
    {
        $total = DocumentSets::countFor('student');
        $by    = static fn ($rows, string $col = 'c') => collect($rows)->mapWithKeys(fn ($r) => [(string) $r->college_code => (int) $r->$col])->all();

        $students = $by(DB::table('users')->where('role', 'student')->where('member_type', 'student')->groupBy('college_code')
            ->selectRaw('college_code, COUNT(*) as c')->get());

        $waiting = DB::table('documents as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('u.role', 'student')->where('d.status', 'submitted')->groupBy('u.college_code')
            ->selectRaw('u.college_code, COUNT(DISTINCT u.id) as c, COUNT(*) as docs')->get();
        $returned = $by(DB::table('documents as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('u.role', 'student')->where('d.status', 'rejected')->groupBy('u.college_code')
            ->selectRaw('u.college_code, COUNT(DISTINCT u.id) as c')->get());
        $approved = collect(DB::table('documents as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('u.role', 'student')->where('d.status', 'approved')->groupBy('u.id', 'u.college_code')
            ->havingRaw('COUNT(*) = ?', [$total])->get(['u.college_code']))
            ->countBy(fn ($r) => (string) $r->college_code)->all();
        $tickets = $by(DB::table('tickets as t')->join('users as u', 'u.id', '=', 't.user_id')
            ->where('t.status', '<>', 'void')->where('u.member_type', 'student')->groupBy('u.college_code')->selectRaw('u.college_code, COUNT(*) as c')->get());

        $sum = max(1, array_sum($students));
        $out = [];
        foreach (config('ereserve.colleges') + ['' => 'No college set'] as $code => $name) {
            $code = (string) $code;
            $n    = $students[$code] ?? 0;
            if ($code === '' && $n === 0) {
                continue;
            }
            $out[$code] = [
                'code' => $code, 'name' => $name, 'students' => $n,
                'waiting'      => (int) (collect($waiting)->firstWhere('college_code', $code)->c ?? 0),
                'docs_waiting' => (int) (collect($waiting)->firstWhere('college_code', $code)->docs ?? 0),
                'returned'     => $returned[$code] ?? 0,
                'approved'     => $approved[$code] ?? 0,
                'tickets'      => $tickets[$code] ?? 0,
                'share'        => round($n / $sum * 100, 1),
                'done'         => $n > 0 ? round(($approved[$code] ?? 0) / $n * 100) : 0.0,
            ];
        }

        return $out;
    }

    /** Issued tickets, newest first. */
    public function issuedTickets(): array
    {
        return array_map(static fn(array $t): array => [
            'id'         => (int) $t['id'],
            'ticket_no'  => $t['ticket_no'],
            'name'       => $t['name'],
            'student_no' => $t['student_no'],
            'program'    => $t['program'],
            'year'       => $t['year_level'],
            'member_type' => $t['member_type'] ?: 'student',
            'college'    => $t['college_code'],
            'status'     => $t['status'],
            'checked_in' => !empty($t['scanned_at']),
            'gate'       => $t['gate'],
            'issued_at'  => $t['issued_at'],
        ], self::rows(
            'SELECT t.id, t.ticket_no, t.status, t.issued_at, u.name, u.student_no, u.program, u.year_level, u.member_type, u.college_code, c.scanned_at, c.gate
             FROM tickets t
             JOIN users u         ON u.id = t.user_id
             LEFT JOIN checkins c ON c.ticket_id = t.id
             ORDER BY t.issued_at DESC, t.id DESC'
        ));
    }

    public function dashboard(): array
    {
        $need = DocumentSets::sqlRequired('u.member_type');
        $row = self::rows(
            'SELECT
                (SELECT COUNT(*) FROM users WHERE role = "student")           AS students,
                (SELECT COUNT(*) FROM documents WHERE status = "submitted")   AS docs_pending,
                (SELECT COUNT(*) FROM documents WHERE status = "approved")    AS docs_approved,
                (SELECT COUNT(*) FROM documents WHERE status = "rejected")    AS docs_rejected,
                (SELECT COUNT(*) FROM tickets WHERE status <> "void")         AS tickets,
                (SELECT COUNT(*) FROM tickets WHERE status = "void")          AS tickets_void,
                (SELECT COUNT(*) FROM checkins)                               AS checked_in,
                (SELECT COUNT(*) FROM users u WHERE u.role = "student"
                    AND (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id) < ' . $need . ') AS incomplete'
        )[0] ?? [];

        $cap     = TicketQuota::capacity();
        $tickets = (int) ($row['tickets'] ?? 0);

        return [
            'students'      => (int) ($row['students'] ?? 0),
            'docs_pending'  => (int) ($row['docs_pending'] ?? 0),
            'docs_approved' => (int) ($row['docs_approved'] ?? 0),
            'docs_rejected' => (int) ($row['docs_rejected'] ?? 0),
            'tickets'       => $tickets,
            'tickets_void'  => (int) ($row['tickets_void'] ?? 0),
            'checked_in'    => (int) ($row['checked_in'] ?? 0),
            'incomplete'    => (int) ($row['incomplete'] ?? 0),
            'capacity'      => $cap,
            'fill_rate'     => round($tickets / max(1, $cap) * 100, 2),
        ];
    }

    private const GROUP_SQL = "CASE WHEN u.member_type = 'sponsor' THEN 'sponsor' WHEN u.member_type IS NULL OR u.member_type = 'student' THEN 'student' ELSE 'employee' END";

    /** Accounts per group (student / employee / sponsor), for the sidebar badges. */
    public function groupCounts(): array
    {
        $out = ['student' => 0, 'employee' => 0, 'sponsor' => 0];
        foreach (DB::table('users as u')->where('u.role', 'student')->selectRaw(self::GROUP_SQL . ' as g, COUNT(*) as c')->groupBy('g')->get() as $r) {
            $out[$r->g] = (int) $r->c;
        }

        return $out;
    }

    /**
     * Students, faculty/staff and sponsors side by side: accounts, tickets, gate check-ins,
     * and how many people (and files) are waiting for SSC review.
     *
     * @return array<string,array{registered:int,tickets:int,checked_in:int,waiting:int,docs:int}>
     */
    public function audienceBreakdown(): array
    {
        $blank = ['registered' => 0, 'tickets' => 0, 'checked_in' => 0, 'waiting' => 0, 'docs' => 0];
        $out   = ['student' => $blank, 'employee' => $blank, 'sponsor' => $blank];
        $g     = self::GROUP_SQL;

        foreach (DB::table('users as u')->where('u.role', 'student')->selectRaw("$g as g, COUNT(*) as c")->groupBy('g')->get() as $r) {
            $out[$r->g]['registered'] = (int) $r->c;
        }
        foreach (DB::table('tickets as t')->join('users as u', 'u.id', '=', 't.user_id')->where('t.status', '<>', 'void')
            ->selectRaw("$g as g, COUNT(*) as c")->groupBy('g')->get() as $r) {
            $out[$r->g]['tickets'] = (int) $r->c;
        }
        foreach (DB::table('checkins as c')->join('tickets as t', 't.id', '=', 'c.ticket_id')->join('users as u', 'u.id', '=', 't.user_id')
            ->selectRaw("$g as g, COUNT(*) as c")->groupBy('g')->get() as $r) {
            $out[$r->g]['checked_in'] = (int) $r->c;
        }
        foreach (DB::table('documents as d')->join('users as u', 'u.id', '=', 'd.user_id')->where('u.role', 'student')->where('d.status', 'submitted')
            ->selectRaw("$g as g, COUNT(DISTINCT u.id) as users, COUNT(*) as docs")->groupBy('g')->get() as $r) {
            $out[$r->g]['waiting'] = (int) $r->users;
            $out[$r->g]['docs']    = (int) $r->docs;
        }

        return $out;
    }

    /**
     * Every programme a college offers (from the config) with how many students and ticket holders
     * each has, so the dashboard can open a college and show its programmes.
     *
     * @return array<string,array{students:int,tickets:int,programs:array<int,array{program:string,students:int,tickets:int}>}>
     */
    public function programsByCollege(): array
    {
        $rows = DB::table('users as u')
            ->leftJoin('tickets as t', fn ($j) => $j->on('t.user_id', '=', 'u.id')->where('t.status', '<>', 'void'))
            ->where('u.role', 'student')
            ->where(fn ($w) => $w->whereNull('u.member_type')->orWhere('u.member_type', 'student'))
            ->whereNotNull('u.college_code')
            ->selectRaw('u.college_code as cc, u.program as pg, COUNT(DISTINCT u.id) as n, COUNT(DISTINCT t.id) as tix')
            ->groupBy('cc', 'pg')->get();

        $out = [];
        foreach (config('ereserve.colleges') as $code => $name) {
            $mine  = $rows->where('cc', $code);
            $known = [];
            foreach (config('ereserve.programs')[$code] ?? [] as $prog) {
                $r       = $mine->firstWhere('pg', $prog);
                $known[] = ['program' => $prog, 'students' => (int) ($r->n ?? 0), 'tickets' => (int) ($r->tix ?? 0)];
            }
            foreach ($mine->filter(fn ($r) => !in_array($r->pg, config('ereserve.programs')[$code] ?? [], true) && $r->pg !== null && $r->pg !== '') as $r) {
                $known[] = ['program' => (string) $r->pg, 'students' => (int) $r->n, 'tickets' => (int) $r->tix];
            }
            $out[$code] = ['students' => array_sum(array_column($known, 'students')), 'tickets' => array_sum(array_column($known, 'tickets')), 'programs' => $known];
        }

        return $out;
    }

    /**
     * What sits inside the "Instructors & staff" and "Sponsors" groups, for the dashboard pop-ups:
     * accounts and tickets per employee category, and the sponsor accounts themselves.
     *
     * @return array{employee:array<int,array{key:string,label:string,accounts:int,tickets:int}>,sponsors:array<int,array{name:string,ticket:bool,email:string}>}
     */
    public function groupDetails(int $sponsorLimit = 20): array
    {
        $rows = DB::table('users as u')
            ->leftJoin('tickets as t', fn ($j) => $j->on('t.user_id', '=', 'u.id')->where('t.status', '<>', 'void'))
            ->where('u.role', 'student')->whereNotNull('u.member_type')
            ->selectRaw('u.member_type as mt, COUNT(DISTINCT u.id) as n, COUNT(DISTINCT t.id) as tix')
            ->groupBy('mt')->get()->keyBy('mt');

        $employee = [];
        foreach (config('ereserve.employee_types') as $key => $label) {
            $employee[] = ['key' => (string) $key, 'label' => (string) $label, 'accounts' => (int) ($rows[$key]->n ?? 0), 'tickets' => (int) ($rows[$key]->tix ?? 0)];
        }

        $sponsors = DB::table('users as u')
            ->where('u.role', 'student')->where('u.member_type', 'sponsor')
            ->orderByDesc('u.created_at')->limit($sponsorLimit)
            ->get(['u.id', 'u.name', 'u.email'])
            ->map(fn ($r) => [
                'name'   => (string) $r->name,
                'email'  => (string) $r->email,
                'ticket' => DB::table('tickets')->where('user_id', $r->id)->where('status', '<>', 'void')->exists(),
            ])->all();

        return ['employee' => $employee, 'sponsors' => $sponsors];
    }

    public function programBreakdown(): array
    {
        return array_map(static fn($r) => ['program' => $r['program'], 'total' => (int) $r['total'], 'ticketed' => (int) $r['ticketed']], self::rows(
            'SELECT u.program, COUNT(DISTINCT u.id) AS total,
                    COUNT(DISTINCT CASE WHEN t.status <> "void" THEN t.id END) AS ticketed
             FROM users u LEFT JOIN tickets t ON t.user_id = u.id
             WHERE u.role = "student" AND u.program IS NOT NULL
             GROUP BY u.program ORDER BY total DESC'
        ));
    }

    /** Check-in log — every ticket holder, scanned or not. */
    public function attendanceLog(): array
    {
        return array_map(static fn($r) => [
            'ticket_no'  => $r['ticket_no'],
            'name'       => $r['name'],
            'student_no' => $r['student_no'],
            'program'    => $r['program'],
            'year'       => $r['year_level'],
            'member_type' => $r['member_type'] ?: 'student',
            'college'    => $r['college_code'],
            'gate'       => $r['gate'] ?? 'Unassigned',
            'checked_in' => !empty($r['scanned_at']),
            'time_in'    => $r['scanned_at'],
            'scanner'    => $r['scanner'] ?? '—',
        ], self::rows(
            'SELECT t.id, t.ticket_no, u.name, u.student_no, u.program, u.year_level, u.member_type, u.college_code, c.gate, c.scanner, c.scanned_at
             FROM tickets t
             JOIN users u         ON u.id = t.user_id
             LEFT JOIN checkins c ON c.ticket_id = t.id
             WHERE t.status <> "void"
             ORDER BY c.scanned_at IS NULL, c.scanned_at ASC, t.ticket_no ASC'
        ));
    }

    public function reviewedDocuments(int $limit = 80): array
    {
        return self::rows(
            'SELECT d.*, u.name AS student_name, u.student_no, v.name AS reviewer
             FROM documents d
             JOIN users u      ON u.id = d.user_id
             LEFT JOIN users v ON v.id = d.reviewed_by
             WHERE d.status <> "submitted"
             ORDER BY d.reviewed_at DESC, d.id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
    }

    public function scanLog(int $limit = 40): array
    {
        return self::rows(
            'SELECT c.gate, c.scanned_at AS time_in, t.ticket_no, u.name, u.student_no, u.program, u.year_level
             FROM checkins c
             JOIN tickets t ON t.id = c.ticket_id
             JOIN users u   ON u.id = t.user_id
             ORDER BY c.scanned_at DESC, c.id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
    }

    public function waitingDocuments(): int
    {
        return (int) DB::table('documents')->where('status', 'submitted')->count();
    }
}
