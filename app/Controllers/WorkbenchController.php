<?php

namespace App\Controllers;

use App\Services\WorkflowService;

/**
 * کارتابل — تنها صفحهٔ کاربران غیر ادمین؛ محتوا بر اساس نقش و بخش‌های منتسب:
 *  آزمونگر: تست سناریوهای بخش‌های خود + ثبت PASS/FAIL/BLOCKED و خطا
 *  برنامه‌نویس: اصلاح خطاهای ارجاعی بخش‌های خود + اعلام رفع
 *  مدیر پروژه: دیدن همهٔ سناریوهای بخش‌های خود + تأیید/رد/بازگشایی/لغو
 */
class WorkbenchController extends BaseController
{
    public function index()
    {
        $uid = (int) session('user_id');
        $role = session('role_code');
        $db = db_connect();

        // فیلترهای کارتابل: پروژه / بخش (زیردرخت) / جست‌وجو / وضعیت / اولویت (یا شدت خطا)
        $f = [
            'project_id' => (int) ($this->request->getGet('project_id') ?? 0),
            'node_id' => (int) ($this->request->getGet('node_id') ?? 0),
            'q' => trim((string) ($this->request->getGet('q') ?? '')),
            'status' => strtoupper((string) $this->request->getGet('status') ?? ''),
            'priority' => strtoupper((string) $this->request->getGet('priority') ?? ''),
        ];
        $nodeIds = $f['node_id'] ? $this->subtreeIds($f['node_id']) : null;

        $data = [
            'role' => $role,
            'role_label' => ['TESTER' => 'آزمونگر', 'DEVELOPER' => 'برنامه‌نویس', 'MANAGER' => 'مدیر پروژه'][$role] ?? 'کاربر',
            'sections' => $this->mySections($uid, $role),
            'f' => $f,
            'f_init' => $this->filterInit($f),
        ];

        if ($role === 'DEVELOPER') {
            $sql = 'SELECT d.id, d.code, d.title, d.severity, d.status, d.created_at, d.fix_notes, d.updated_at,
                           p.name project_name, p.id project_id, tc.id tc_id, tc.code tc_code, tc.title tc_title,
                           n.name node_name, n.id node_id, ur.display_name reporter_name
                    FROM defects d
                    JOIN projects p ON p.id = d.project_id
                    JOIN test_executions e ON e.id = d.execution_id
                    JOIN test_run_cases rc ON rc.id = e.test_run_case_id
                    JOIN test_cases tc ON tc.id = rc.test_case_id
                    JOIN project_nodes n ON n.id = tc.node_id
                    LEFT JOIN users ur ON ur.id = d.reported_by
                    WHERE d.developer_id = ?';
            $params = [$uid];
            if ($f['project_id']) { $sql .= ' AND p.id = ?'; $params[] = $f['project_id']; }
            if ($nodeIds) { $sql .= ' AND tc.node_id IN (' . implode(',', array_map('intval', $nodeIds)) . ')'; }
            if ($f['q'] !== '') { $sql .= ' AND (d.title LIKE ? OR d.code LIKE ? OR d.description LIKE ? OR tc.title LIKE ? OR tc.code LIKE ?)';
                $like = "%{$f['q']}%"; array_push($params, $like, $like, $like, $like, $like); }
            if ($f['status'] !== '') { $sql .= ' AND d.status = ?'; $params[] = $f['status']; }
            if ($f['priority'] !== '') { $sql .= ' AND d.severity = ?'; $params[] = $f['priority']; }
            $sql .= ' ORDER BY FIELD(d.status,"OPEN","REOPENED","IN_PROGRESS","FIXED_PENDING_RETEST","VERIFIED","CLOSED"), d.id DESC LIMIT 300';
            $data['defects'] = $db->query($sql, $params)->getResultArray();
        } else {
            $col = $role === 'TESTER' ? 'na.tester_id' : 'na.manager_id';
            $sql = "SELECT rc.id, rc.workflow_status, tc.id tc_id, tc.code, tc.title, tc.priority, tc.status tc_status,
                           tc.created_at case_created_at, p.name project_name, p.id project_id, p.code project_code,
                           n.name node_name, n.id node_id, tr.name run_name, tr.status run_status,
                           ut.display_name tester_name, ud.display_name developer_name, um.display_name manager_name,
                           (SELECT e.result FROM test_executions e WHERE e.test_run_case_id = rc.id ORDER BY e.execution_no DESC LIMIT 1) last_result,
                           (SELECT COUNT(*) FROM test_executions e2 WHERE e2.test_run_case_id = rc.id) exec_count,
                           (SELECT COUNT(*) FROM defects d2 WHERE d2.test_run_case_id = rc.id AND d2.status NOT IN ('VERIFIED','CLOSED')) open_defects
                    FROM test_run_cases rc
                    JOIN test_cases tc ON tc.id = rc.test_case_id
                    JOIN projects p ON p.id = tc.project_id
                    JOIN project_nodes n ON n.id = tc.node_id
                    JOIN test_runs tr ON tr.id = rc.test_run_id
                    LEFT JOIN node_assignments na ON na.node_id = tc.node_id
                    LEFT JOIN users ut ON ut.id = na.tester_id
                    LEFT JOIN users ud ON ud.id = na.developer_id
                    LEFT JOIN users um ON um.id = na.manager_id
                    WHERE $col = ?";
            $params = [$uid];
            if ($f['project_id']) { $sql .= ' AND p.id = ?'; $params[] = $f['project_id']; }
            if ($nodeIds) { $sql .= ' AND tc.node_id IN (' . implode(',', array_map('intval', $nodeIds)) . ')'; }
            if ($f['q'] !== '') { $sql .= ' AND (tc.title LIKE ? OR tc.code LIKE ? OR tc.objective LIKE ? OR tc.expected_result LIKE ?)';
                $like = "%{$f['q']}%"; array_push($params, $like, $like, $like, $like); }
            if ($f['status'] !== '') { $sql .= ' AND rc.workflow_status = ?'; $params[] = $f['status']; }
            if ($f['priority'] !== '') { $sql .= ' AND tc.priority = ?'; $params[] = $f['priority']; }
            $sql .= ' ORDER BY rc.id DESC LIMIT 300';
            $items = $db->query($sql, $params)->getResultArray();
            $data['items'] = $items;
            $data['counts'] = $this->tabCounts($items);
        }
        if ($role === 'DEVELOPER') {
            $data['counts'] = [
                'todo' => count(array_filter($data['defects'], fn($d) => in_array($d['status'], ['OPEN', 'REOPENED', 'IN_PROGRESS'], true))),
                'waiting' => count(array_filter($data['defects'], fn($d) => $d['status'] === 'FIXED_PENDING_RETEST')),
                'done' => count(array_filter($data['defects'], fn($d) => in_array($d['status'], ['VERIFIED', 'CLOSED'], true))),
            ];
        }
        return view('workbench/index', $data);
    }

    /** نام پروژه/بخش فیلتر فعال — برای نمایش مقدار انتخاب‌شده در سلکتور */
    private function filterInit(array $f): ?array
    {
        if (!$f['project_id'] && !$f['node_id']) return null;
        $db = db_connect();
        if ($f['node_id']) {
            $row = $db->query(
                'SELECT n.id nid, n.name nname, p.id pid, p.name pname FROM project_nodes n JOIN projects p ON p.id = n.project_id WHERE n.id = ?', [$f['node_id']]
            )->getFirstRow('array');
            if (!$row) return null;
            return ['projectId' => (int) $row['pid'], 'projectName' => $row['pname'], 'nodeId' => (int) $row['nid'], 'nodeName' => $row['nname']];
        }
        $p = $db->table('projects')->select('name')->where('id', $f['project_id'])->get()->getFirstRow('array');
        return $p ? ['projectId' => $f['project_id'], 'projectName' => $p['name'], 'nodeId' => null, 'nodeName' => null] : null;
    }

    /** شناسهٔ گره + همهٔ فرزندان فعال */
    private function subtreeIds(int $nodeId): array
    {
        $db = db_connect();
        $ids = [$nodeId];
        $queue = [$nodeId];
        $guard = 0;
        while ($queue && $guard++ < 500) {
            $rows = $db->table('project_nodes')->select('id')->whereIn('parent_id', array_map('intval', $queue))->where('is_active', 1)->get()->getResultArray();
            $queue = [];
            foreach ($rows as $r) { $ids[] = (int) $r['id']; $queue[] = (int) $r['id']; }
        }
        return array_values(array_unique($ids));
    }

    private function tabCounts(array $items): array
    {
        $active = ['READY', 'IN_TEST', 'READY_RETEST'];
        return [
            'todo' => count(array_filter($items, fn($i) => in_array($i['workflow_status'], $active, true))),
            'blocked' => count(array_filter($items, fn($i) => $i['workflow_status'] === 'BLOCKED')),
            'waiting' => count(array_filter($items, fn($i) => in_array($i['workflow_status'], ['IN_FIX', 'PENDING_APPROVAL'], true))),
            'done' => count(array_filter($items, fn($i) => in_array($i['workflow_status'], ['APPROVED', 'REJECTED', 'CANCELLED'], true))),
        ];
    }

    /** بخش‌های منتسب به کاربر در نقش فعلی — ساختار درختی برای سلکتور کارتابل */
    private function mySections(int $uid, string $role): array
    {
        $col = ['TESTER' => 'tester_id', 'DEVELOPER' => 'developer_id', 'MANAGER' => 'manager_id'][$role] ?? 'tester_id';
        $rows = db_connect()->query(
            "SELECT n.id, n.name, n.parent_id, p.id project_id, p.name project_name
             FROM node_assignments na
             JOIN project_nodes n ON n.id = na.node_id
             JOIN projects p ON p.id = n.project_id
             WHERE na.$col = ? AND n.is_active = 1 AND p.status = 'ACTIVE'
             ORDER BY p.name, n.name LIMIT 200", [$uid]
        )->getResultArray();
        $projects = [];
        foreach ($rows as $r) $projects[$r['project_id']]['nodes'][] = $r;
        $out = [];
        foreach ($projects as $pid => $p) {
            $byParent = [];
            foreach ($p['nodes'] as $n) $byParent[$n['parent_id'] ?: 0][] = ['id' => (int) $n['id'], 'name' => $n['name']];
            $attach = function ($parentId) use (&$attach, $byParent) {
                $out2 = [];
                foreach ($byParent[$parentId] ?? [] as $n) { $n['children'] = $attach($n['id']); $out2[] = $n; }
                return $out2;
            };
            $out[] = ['id' => (int) $pid, 'name' => $p['nodes'][0]['project_name'], 'nodes' => $attach(0)];
        }
        return $out;
    }

    /** جزئیات آیتم برای مودال (متعلق به بخش کاربر) */
    public function detail(int $runCaseId)
    {
        $wf = new WorkflowService();
        $c = $wf->loadCase($runCaseId);
        $uid = (int) session('user_id');
        $role = session('role_code');
        $isTester = (int) $c['tester_id'] === $uid && $role === 'TESTER';
        $isManager = (int) $c['manager_id'] === $uid && $role === 'MANAGER';
        if (!$isTester && !$isManager && !((int) $c['developer_id'] === $uid && $role === 'DEVELOPER') && !session('is_admin'))
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => t('wf.not_assigned')]);

        $db = db_connect();
        $case = $db->table('test_cases')->where('id', $c['test_case_id'])->get()->getFirstRow('array');
        $steps = $db->table('test_case_steps')->where('test_case_id', $c['test_case_id'])->orderBy('step_order')->get()->getResultArray();
        $executions = $db->query(
            'SELECT e.execution_no, e.result, e.actual_result, e.notes, e.executed_at, u.display_name tester_name
             FROM test_executions e JOIN users u ON u.id = e.tester_id WHERE e.test_run_case_id = ? ORDER BY e.id DESC', [$runCaseId]
        )->getResultArray();
        $defects = $db->query(
            'SELECT d.id, d.code, d.title, d.severity, d.status, d.description, d.fix_notes, d.created_at
             FROM defects d WHERE d.test_run_case_id = ? ORDER BY d.id DESC', [$runCaseId]
        )->getResultArray();
        $history = $db->query(
            'SELECT wh.from_status, wh.to_status, wh.reason, wh.created_at, u.display_name actor_name
             FROM workflow_history wh JOIN users u ON u.id = wh.actor_user_id WHERE wh.test_run_case_id = ? ORDER BY wh.id DESC LIMIT 30', [$runCaseId]
        )->getResultArray();
        return $this->response->setJSON([
            'ok' => true,
            'case' => [
                'code' => $case['code'], 'title' => $case['title'], 'objective' => $case['objective'],
                'preconditions' => $case['preconditions'], 'test_data' => $case['test_data'], 'expected_result' => $case['expected_result'],
                'priority' => $case['priority'], 'node' => $wf->nodePath((int) $case['node_id']),
                'project' => $c['project_name'], 'run' => $c['run_name'],
                'workflow_status' => $c['workflow_status'], 'run_status' => $c['run_status'],
                'tester' => null, 'developer' => null, 'manager' => null,
            ],
            'steps' => $steps, 'executions' => $executions, 'defects' => $defects, 'history' => $history,
            'can' => ['start' => $isTester, 'execute' => $isTester, 'unblock' => $isTester || $isManager, 'approve' => $isManager, 'reject' => $isManager, 'reopen' => $isManager, 'cancel' => $isManager],
        ]);
    }

    /* ------------------------- اقدام‌ها ------------------------- */

    public function start(int $id)
    {
        return $this->action(fn() => (new WorkflowService())->start($id, (int) session('user_id')));
    }

    public function execute(int $id)
    {
        return $this->action(function () use ($id) {
            $result = strtoupper((string) $this->request->getPost('result'));
            $actual = (string) $this->request->getPost('actual_result');
            $notes = (string) $this->request->getPost('notes');
            $defect = null;
            if ($result === 'FAIL') {
                $defect = [
                    'title' => (string) $this->request->getPost('defect_title'),
                    'severity' => strtoupper((string) $this->request->getPost('defect_severity')),
                    'description' => (string) $this->request->getPost('defect_description'),
                ];
            }
            return (new WorkflowService())->execute($id, (int) session('user_id'), $result, $actual, $defect, $notes);
        });
    }

    public function unblock(int $id)
    {
        $reason = (string) $this->request->getPost('reason');
        return $this->action(fn() => (new WorkflowService())->unblock($id, (int) session('user_id'), $reason));
    }

    public function approve(int $id)
    {
        return $this->action(fn() => (new WorkflowService())->approve($id, (int) session('user_id'), (string) $this->request->getPost('reason')));
    }

    public function reject(int $id)
    {
        return $this->action(fn() => (new WorkflowService())->reject($id, (int) session('user_id'), (string) $this->request->getPost('reason')));
    }

    public function reopen(int $id)
    {
        return $this->action(fn() => (new WorkflowService())->reopen($id, (int) session('user_id'), (string) $this->request->getPost('reason')));
    }

    public function cancel(int $id)
    {
        return $this->action(fn() => (new WorkflowService())->cancel($id, (int) session('user_id'), (string) $this->request->getPost('reason')));
    }

    public function startFix(int $defectId)
    {
        return $this->action(fn() => (new WorkflowService())->startFix($defectId, (int) session('user_id')));
    }

    public function declareFixed(int $defectId)
    {
        return $this->action(fn() => (new WorkflowService())->declareFixed($defectId, (int) session('user_id'), (string) $this->request->getPost('fix_notes')));
    }

    private function action(callable $fn)
    {
        try {
            $fn();
            return $this->response->setJSON(['ok' => true, 'message' => t('common.done')]);
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'message' => t('common.network_error')]);
        }
    }
}
