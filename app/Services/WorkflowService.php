<?php

namespace App\Services;

/**
 * ماشین حالت گردش کار (§5 سند) — تنها نقطهٔ مجاز تغییر workflow_status.
 * هر انتقال در یک تراکنش: قفل ردیف + ثبت execution/defect + workflow_history + notification.
 */
class WorkflowService
{
    public const READY = 'READY';
    public const IN_TEST = 'IN_TEST';
    public const IN_FIX = 'IN_FIX';
    public const READY_RETEST = 'READY_RETEST';
    public const BLOCKED = 'BLOCKED';
    public const PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';
    public const CANCELLED = 'CANCELLED';

    /** برچسب فارسی + کلاس رنگی پیل وضعیت */
    public static function statusMeta(): array
    {
        $out = [];
        foreach (self::statusClasses() as $code => $class) $out[$code] = [t('status.' . $code), $class];
        return $out;
    }

    /** کلاس رنگی وضعیت‌ها (مستقل از زبان) */
    public static function statusClasses(): array
    {
        return [
            'READY' => 'st-ready', 'IN_TEST' => 'st-intest', 'IN_FIX' => 'st-infix',
            'READY_RETEST' => 'st-retest', 'BLOCKED' => 'st-blocked', 'PENDING_APPROVAL' => 'st-approval',
            'APPROVED' => 'st-approved', 'REJECTED' => 'st-rejected', 'CANCELLED' => 'st-cancelled',
        ];
    }

    public static function priorityMeta(): array
    {
        return [
            'LOW' => [t('pr.LOW'), 'pr-low'], 'MEDIUM' => [t('pr.MEDIUM'), 'pr-medium'],
            'HIGH' => [t('pr.HIGH'), 'pr-high'], 'CRITICAL' => [t('pr.CRITICAL'), 'pr-critical'],
        ];
    }

    public static function severityMeta(): array
    {
        return [
            'LOW' => [t('pr.LOW'), 'pr-low'], 'MEDIUM' => [t('pr.MEDIUM'), 'pr-medium'],
            'HIGH' => [t('pr.HIGH'), 'pr-high'], 'CRITICAL' => [t('pr.CRITICAL'), 'pr-critical'],
        ];
    }

    public static function defectStatusMeta(): array
    {
        return [
            'OPEN' => [t('dstatus.OPEN'), 'st-infix'], 'IN_PROGRESS' => [t('dstatus.IN_PROGRESS'), 'st-intest'],
            'FIXED_PENDING_RETEST' => [t('dstatus.FIXED_PENDING_RETEST'), 'st-retest'],
            'VERIFIED' => [t('dstatus.VERIFIED'), 'st-approved'], 'REOPENED' => [t('dstatus.REOPENED'), 'st-rejected'],
            'CLOSED' => [t('dstatus.CLOSED'), 'st-cancelled'],
        ];
    }

    private $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    public function db()
    {
        return $this->db;
    }

    /** بارگذاری آیتم دوره با سناریو، گره، انتساب و دوره — با قفل برای نوشتن */
    public function loadCase(int $runCaseId, bool $forUpdate = false): array
    {
        $sql = 'SELECT rc.*, tc.code tc_code, tc.title, tc.priority, tc.project_id, tc.node_id, tc.current_revision,
                       n.name node_name, p.name project_name, p.code project_code,
                       na.tester_id, na.developer_id, na.manager_id,
                       tr.name run_name, tr.status run_status
                FROM test_run_cases rc
                JOIN test_cases tc ON tc.id = rc.test_case_id
                JOIN project_nodes n ON n.id = tc.node_id
                JOIN projects p ON p.id = tc.project_id
                LEFT JOIN node_assignments na ON na.node_id = tc.node_id
                JOIN test_runs tr ON tr.id = rc.test_run_id
                WHERE rc.id = ?' . ($forUpdate ? ' FOR UPDATE' : '');
        $row = $this->db->query($sql, [$runCaseId])->getFirstRow('array');
        if (!$row) throw new \RuntimeException(t('wf.item_not_found'));;
        return $row;
    }

    public function loadDefect(int $defectId, bool $forUpdate = false): array
    {
        $sql = 'SELECT d.*, rc.id run_case_id, rc.workflow_status, tc.id test_case_id, tc.code tc_code, tc.title tc_title,
                       tc.project_id, tc.node_id, n.name node_name, p.name project_name,
                       na.tester_id, na.developer_id, na.manager_id, tr.name run_name
                FROM defects d
                JOIN test_executions e ON e.id = d.execution_id
                JOIN test_run_cases rc ON rc.id = e.test_run_case_id
                JOIN test_cases tc ON tc.id = rc.test_case_id
                JOIN project_nodes n ON n.id = tc.node_id
                JOIN projects p ON p.id = tc.project_id
                LEFT JOIN node_assignments na ON na.node_id = tc.node_id
                JOIN test_runs tr ON tr.id = rc.test_run_id
                WHERE d.id = ?' . ($forUpdate ? ' FOR UPDATE' : '');
        $row = $this->db->query($sql, [$defectId])->getFirstRow('array');
        if (!$row) throw new \RuntimeException(t('wf.defect_not_found'));;
        return $row;
    }

    /* ----------------------------- انتقال‌ها ----------------------------- */

    /** شروع تست: READY → IN_TEST توسط آزمونگر مسئول گره */
    public function start(int $runCaseId, int $userId): void
    {
        $this->db->transBegin();
        try {
            $c = $this->loadCase($runCaseId, true);
            $this->assertRunOpen($c);
            $this->assertTester($c, $userId);
            if ($c['workflow_status'] !== self::READY) $this->fail(t('wf.start_only_ready'));
            $this->transition($c, self::IN_TEST, $userId, 'شروع تست توسط آزمونگر');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * ثبت نتیجه توسط آزمونگر: PASS → تأیید مدیر | FAIL → ایجاد Defect و رفتن به برنامه‌نویس | BLOCKED → مسدود
     * $defect = ['title'=>..,'severity'=>..,'description'=>..] فقط برای FAIL الزامی است.
     */
    public function execute(int $runCaseId, int $userId, string $result, string $actualResult, ?array $defect = null, ?string $notes = null): array
    {
        $out = ['execution_id' => 0, 'defect_id' => 0];
        $this->db->transBegin();
        try {
            $c = $this->loadCase($runCaseId, true);
            $this->assertRunOpen($c);
            $this->assertTester($c, $userId);
            if (!in_array($c['workflow_status'], [self::READY, self::IN_TEST, self::READY_RETEST], true))
                $this->fail(t('wf.exec_not_allowed', ['status' => t('status.' . $c['workflow_status'])]));
            if (!in_array($result, ['PASS', 'FAIL', 'BLOCKED'], true)) $this->fail(t('wf.invalid_result'));
            if (mb_strlen(trim($actualResult)) < 3) $this->fail(t('wf.actual_min'));

            $executionNo = (int) $this->db->query('SELECT COALESCE(MAX(execution_no),0)+1 n FROM test_executions WHERE test_run_case_id=?', [$runCaseId])->getRowArray()['n'];
            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('test_executions')->insert([
                'test_run_case_id' => $runCaseId, 'test_case_revision_id' => $c['test_case_revision_id'],
                'execution_no' => $executionNo, 'tester_id' => $userId, 'result' => $result,
                'actual_result' => $actualResult, 'notes' => $notes, 'executed_at' => $now, 'created_at' => $now,
            ]);
            $out['execution_id'] = $this->db->insertID();

            if ($result === 'PASS') {
                $this->transition($c, self::PENDING_APPROVAL, $userId, 'ثبت نتیجهٔ موفق؛ ارجاع برای تأیید مدیر پروژه');
                $this->notifyUser($c['manager_id'], $c['project_id'], 'APPROVAL', 'در انتظار تأیید شما', 'سناریو «' . $c['tc_code'] . ' — ' . $c['title'] . '» با نتیجهٔ PASS در انتظار تأیید شماست.', '/workbench');
            } elseif ($result === 'FAIL') {
                if (!$defect || mb_strlen(trim($defect['title'] ?? '')) < 3) $this->fail(t('wf.fail_needs_defect'));
                $severity = strtoupper($defect['severity'] ?? 'MEDIUM');
                if (!isset(self::severityMeta()[$severity])) $severity = 'MEDIUM';
                $defectCode = $this->nextDefectCode($c['project_id'], $c['project_code']);
                $this->db->table('defects')->insert([
                    'project_id' => $c['project_id'], 'test_run_case_id' => $runCaseId, 'execution_id' => $out['execution_id'],
                    'code' => $defectCode, 'title' => trim($defect['title']), 'description' => trim($defect['description'] ?? '') ?: $actualResult,
                    'severity' => $severity, 'status' => 'OPEN', 'developer_id' => $c['developer_id'],
                    'reported_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $out['defect_id'] = $this->db->insertID();
                $this->transition($c, self::IN_FIX, $userId, 'ثبت خطای «' . $defectCode . '» و ارجاع به برنامه‌نویس');
                $this->notifyUser($c['developer_id'], $c['project_id'], 'DEFECT', 'خطای جدید برای اصلاح', 'سناریو «' . $c['tc_code'] . '» با خطای ' . $defectCode . ' برای اصلاح به شما ارجاع شد.', '/workbench');
            } else { // BLOCKED
                $this->transition($c, self::BLOCKED, $userId, 'مسدودسازی: ' . mb_substr(trim($actualResult), 0, 180));
                $this->notifyUser($c['manager_id'], $c['project_id'], 'BLOCKED', 'سناریو مسدود شد', 'سناریو «' . $c['tc_code'] . '» توسط آزمونگر مسدود شد: ' . mb_substr($actualResult, 0, 120), '/workbench');
            }
            $this->db->transCommit();
            return $out;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** شروع اصلاح توسط برنامه‌نویس مسئول: Defect → IN_PROGRESS */
    public function startFix(int $defectId, int $userId): void
    {
        $this->db->transBegin();
        try {
            $d = $this->loadDefect($defectId, true);
            if ((int) $d['developer_id'] !== $userId) $this->fail(t('wf.not_your_defect'));
            if (!in_array($d['status'], ['OPEN', 'REOPENED'], true)) $this->fail(t('wf.not_allowed_from', ['status' => t('dstatus.' . $d['status'])]));
            $this->db->table('defects')->update(['status' => 'IN_PROGRESS', 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $defectId]);
            $this->db->table('workflow_history')->insert([
                'test_run_case_id' => $d['run_case_id'], 'from_status' => $d['workflow_status'], 'to_status' => $d['workflow_status'],
                'actor_user_id' => $userId, 'reason' => 'شروع اصلاح خطای ' . $d['code'], 'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** اعلام رفع توسط برنامه‌نویس: Defect → FIXED_PENDING_RETEST و آیتم → READY_RETEST */
    public function declareFixed(int $defectId, int $userId, string $fixNotes): void
    {
        if (mb_strlen(trim($fixNotes)) < 3) throw new \RuntimeException(t('wf.fix_notes_min'));
        $this->db->transBegin();
        try {
            $d = $this->loadDefect($defectId, true);
            if ((int) $d['developer_id'] !== $userId) $this->fail(t('wf.not_your_defect'));
            if (!in_array($d['status'], ['OPEN', 'IN_PROGRESS', 'REOPENED'], true)) $this->fail(t('wf.not_allowed_from', ['status' => t('dstatus.' . $d['status'])]));
            $this->db->table('defects')->update(['status' => 'FIXED_PENDING_RETEST', 'fix_notes' => trim($fixNotes), 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $defectId]);
            $c = $this->loadCase((int) $d['run_case_id'], true);
            $this->assertRunOpen($c);
            if ($c['workflow_status'] === self::IN_FIX) {
                $this->transition($c, self::READY_RETEST, $userId, 'اعلام رفع خطای ' . $d['code']);
            } else {
                $this->db->table('workflow_history')->insert([
                    'test_run_case_id' => $c['id'], 'from_status' => $c['workflow_status'], 'to_status' => $c['workflow_status'],
                    'actor_user_id' => $userId, 'reason' => 'اعلام رفع خطای ' . $d['code'], 'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }
            $this->notifyUser($c['tester_id'], $c['project_id'], 'RETEST', 'آمادهٔ تست مجدد', 'اصلاح خطای ' . $d['code'] . ' اعلام شد؛ سناریو «' . $c['tc_code'] . '» آمادهٔ تست مجدد است.', '/workbench');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** رفع مانع: BLOCKED → READY_RETEST (آزمونگر یا مدیر گره) */
    public function unblock(int $runCaseId, int $userId, string $reason): void
    {
        $this->guardedTransition($runCaseId, $userId, self::BLOCKED, self::READY_RETEST, 'رفع مانع: ' . $reason, fn($c, $uid) => $this->isTesterOrManager($c, $uid), $reason);
        $c = $this->loadCase($runCaseId);
        $this->notifyUser($c['tester_id'], $c['project_id'], 'RETEST', 'مانع رفع شد', 'سناریو «' . $c['tc_code'] . '» آمادهٔ تست مجدد است.', '/workbench');
    }

    /** تأیید مدیر: PENDING_APPROVAL → APPROVED + خطاهای رفع‌شدهٔ همین آیتم VERIFIED می‌شوند */
    public function approve(int $runCaseId, int $userId, string $reason = ''): void
    {
        $this->guardedTransition($runCaseId, $userId, self::PENDING_APPROVAL, self::APPROVED, 'تأیید نهایی' . ($reason ? ': ' . $reason : ''), fn($c, $uid) => $this->isManager($c, $uid));
        db_connect()->table('defects')
            ->where('test_run_case_id', $runCaseId)->where('status', 'FIXED_PENDING_RETEST')
            ->update(['status' => 'VERIFIED', 'updated_at' => gmdate('Y-m-d H:i:s')]);
        $c = $this->loadCase($runCaseId);
        $this->notifyUser($c['tester_id'], $c['project_id'], 'APPROVED', 'سناریو تأیید شد', 'سناریو «' . $c['tc_code'] . '» توسط مدیر پروژه تأیید شد.', '/workbench');
    }

    /** رد نهایی مدیر: PENDING_APPROVAL → REJECTED (دلیل الزامی) */
    public function reject(int $runCaseId, int $userId, string $reason): void
    {
        if (mb_strlen(trim($reason)) < 3) throw new \RuntimeException(t('wf.reason_required'));
        $this->guardedTransition($runCaseId, $userId, self::PENDING_APPROVAL, self::REJECTED, 'رد نهایی: ' . $reason, fn($c, $uid) => $this->isManager($c, $uid), $reason);
        $c = $this->loadCase($runCaseId);
        $this->notifyUser($c['tester_id'], $c['project_id'], 'REJECTED', 'نتیجهٔ سناریو رد شد', 'نتیجهٔ سناریو «' . $c['tc_code'] . '» توسط مدیر رد شد: ' . mb_substr($reason, 0, 140), '/workbench');
    }

    /** بازگشایی: وضعیت‌های پایانی → READY_RETEST (مدیر گره یا ادمین) */
    public function reopen(int $runCaseId, int $userId, string $reason): void
    {
        if (mb_strlen(trim($reason)) < 3) throw new \RuntimeException(t('wf.reason_required'));
        $this->guardedTransition($runCaseId, $userId, self::PENDING_APPROVAL, self::READY_RETEST, 'بازگشایی: ' . $reason, fn($c, $uid) => $this->isManager($c, $uid), $reason, [self::APPROVED, self::REJECTED, self::CANCELLED, self::PENDING_APPROVAL]);
        $c = $this->loadCase($runCaseId);
        $this->notifyUser($c['tester_id'], $c['project_id'], 'RETEST', 'سناریو بازگشایی شد', 'سناریو «' . $c['tc_code'] . '» برای تست مجدد بازگشایی شد.', '/workbench');
    }

    /** لغو: وضعیت‌های فعال → CANCELLED (مدیر گره) */
    public function cancel(int $runCaseId, int $userId, string $reason): void
    {
        if (mb_strlen(trim($reason)) < 3) throw new \RuntimeException(t('wf.reason_required'));
        $this->guardedTransition($runCaseId, $userId, self::READY, self::CANCELLED, 'لغو: ' . $reason, fn($c, $uid) => $this->isManager($c, $uid), $reason,
            [self::READY, self::IN_TEST, self::IN_FIX, self::READY_RETEST, self::BLOCKED, self::PENDING_APPROVAL]);
        $c = $this->loadCase($runCaseId);
        $this->notifyUser($c['tester_id'], $c['project_id'], 'CANCELLED', 'سناریو لغو شد', 'سناریو «' . $c['tc_code'] . '» توسط مدیر لغو شد: ' . mb_substr($reason, 0, 140), '/workbench');
    }

    /* ----------------------------- داخلی ----------------------------- */

    private function guardedTransition(int $runCaseId, int $userId, string $from, string $to, string $reason, callable $guard, string $rawReason = '', array $fromSet = []): void
    {
        $fromSet = $fromSet ?: [$from];
        $this->db->transBegin();
        try {
            $c = $this->loadCase($runCaseId, true);
            $this->assertRunOpen($c);
            if (!$guard($c, $userId)) $this->fail(t('wf.no_permission_item'));
            if (!in_array($c['workflow_status'], $fromSet, true))
                $this->fail(t('wf.not_allowed_from', ['status' => t('status.' . $c['workflow_status'])]));
            $this->transition($c, $to, $userId, $reason);
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function transition(array $c, string $to, int $actorId, string $reason): void
    {
        $this->db->table('test_run_cases')->update(['workflow_status' => $to], ['id' => $c['id']]);
        $this->db->table('workflow_history')->insert([
            'test_run_case_id' => $c['id'], 'from_status' => $c['workflow_status'], 'to_status' => $to,
            'actor_user_id' => $actorId, 'reason' => $reason, 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function assertRunOpen(array $c): void
    {
        if ($c['run_status'] !== 'OPEN') $this->fail(t('wf.run_closed'));
    }

    private function assertTester(array $c, int $userId): void
    {
        if ((int) $c['tester_id'] !== $userId) $this->fail(t('wf.only_tester'));
    }

    private function isTesterOrManager(array $c, int $userId): bool
    {
        return (int) $c['tester_id'] === $userId || $this->isManager($c, $userId);
    }

    private function isManager(array $c, int $userId): bool
    {
        return (int) $c['manager_id'] === $userId;
    }

    private function notifyUser(?int $userId, int $projectId, string $type, string $title, string $message, string $url): void
    {
        if (!$userId) return;
        $this->db->table('notifications')->insert([
            'user_id' => $userId, 'project_id' => $projectId, 'type' => $type, 'title' => $title,
            'message' => $message, 'target_url' => $url, 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function nextDefectCode(int $projectId, string $projectCode): string
    {
        $n = (int) $this->db->query('SELECT COALESCE(MAX(id),0)+1 n FROM defects')->getRowArray()['n'];
        return $projectCode . '-D' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function fail(string $msg): void
    {
        throw new \RuntimeException($msg);
    }

    /* ----------------------------- کوئری‌های مشترک ----------------------------- */

    /** درخت مسیر گره با نام اجداد */
    public function nodePath(int $nodeId): string
    {
        $path = [];
        $id = $nodeId;
        $guard = 0;
        while ($id && $guard++ < 32) {
            $n = $this->db->table('project_nodes')->select('name,parent_id')->where('id', $id)->get()->getFirstRow('array');
            if (!$n) break;
            array_unshift($path, $n['name']);
            $id = $n['parent_id'] ? (int) $n['parent_id'] : 0;
        }
        return implode(' ← ', $path);
    }

    /** دورهٔ باز پروژه؛ در صورت نبود، ایجاد می‌کند */
    public function ensureOpenRun(int $projectId, int $userId): int
    {
        $run = $this->db->table('test_runs')->where('project_id', $projectId)->where('status', 'OPEN')->orderBy('id', 'ASC')->get()->getFirstRow('array');
        if ($run) return (int) $run['id'];
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('test_runs')->insert([
            'project_id' => $projectId, 'name' => 'دورهٔ جاری', 'description' => 'به‌صورت خودکار هنگام ثبت سناریو ایجاد شد',
            'status' => 'OPEN', 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) $this->db->insertID();
    }

    /** افزودن سناریوی ACTIVE به دورهٔ باز (اتمیک + idempotent) */
    public function addCaseToOpenRun(int $testCaseId, int $userId): int
    {
        $tc = $this->db->table('test_cases')->where('id', $testCaseId)->get()->getFirstRow('array');
        if (!$tc) $this->fail(t('wf.case_not_found'));
        $runId = $this->ensureOpenRun((int) $tc['project_id'], $userId);
        $existing = $this->db->table('test_run_cases')->where('test_run_id', $runId)->where('test_case_id', $testCaseId)->get()->getFirstRow('array');
        if ($existing) return (int) $existing['id'];
        $rev = $this->db->table('test_case_revisions')->where('test_case_id', $testCaseId)->orderBy('revision_no', 'DESC')->get()->getFirstRow('array');
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('test_run_cases')->insert([
            'test_run_id' => $runId, 'test_case_id' => $testCaseId,
            'test_case_revision_id' => $rev['id'] ?? 0, 'workflow_status' => self::READY, 'lock_version' => 1, 'added_at' => $now,
        ]);
        $rcId = (int) $this->db->insertID();
        $this->db->table('workflow_history')->insert([
            'test_run_case_id' => $rcId, 'from_status' => null, 'to_status' => self::READY,
            'actor_user_id' => $userId, 'reason' => 'افزوده شدن به دورهٔ تست', 'created_at' => $now,
        ]);
        return $rcId;
    }

    /** snapshot کامل سناریو برای revision جدید */
    public function snapshotRevision(int $testCaseId, int $userId, string $reason): int
    {
        $tc = $this->db->table('test_cases')->where('id', $testCaseId)->get()->getFirstRow('array');
        $steps = $this->db->table('test_case_steps')->where('test_case_id', $testCaseId)->orderBy('step_order')->get()->getResultArray();
        $revNo = (int) ($tc['current_revision'] ?? 0) + 1;
        $snapshot = [
            'code' => $tc['code'], 'title' => $tc['title'], 'objective' => $tc['objective'],
            'preconditions' => $tc['preconditions'], 'test_data' => $tc['test_data'],
            'expected_result' => $tc['expected_result'], 'priority' => $tc['priority'], 'test_type' => $tc['test_type'],
            'steps' => array_map(fn($s) => ['order' => $s['step_order'], 'instruction' => $s['instruction'], 'expected' => $s['expected_result']], $steps),
        ];
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('test_case_revisions')->insert([
            'test_case_id' => $testCaseId, 'revision_no' => $revNo, 'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'reason' => $reason ?: 'ویرایش سناریو', 'created_by' => $userId, 'created_at' => $now,
        ]);
        $this->db->table('test_cases')->update(['current_revision' => $revNo], ['id' => $testCaseId]);
        return (int) $this->db->insertID();
    }
}
