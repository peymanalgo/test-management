<?php

namespace App\Controllers;

use App\Services\WorkflowService;

/** گرید سراسری سناریوها — انتخاب پروژه/بخش با دراپ‌داون درختی + جزئیات فقط‌خواندنی */
class TestCasesController extends BaseController
{
    public function index()
    {
        $db = db_connect();
        $projects = $db->query(
            'SELECT p.id, p.code, p.name FROM projects p WHERE p.status = "ACTIVE" ORDER BY p.name'
        )->getResultArray();
        $nodesByProject = [];
        foreach ($projects as $p) {
            $rows = $db->query(
                'SELECT n.id, n.parent_id, n.name FROM project_nodes n WHERE n.project_id = ? AND n.is_active = 1 ORDER BY n.sort_order, n.id', [$p['id']]
            )->getResultArray();
            $nodesByProject[$p['id']] = $this->nest($rows);
        }
        return view('test_cases/index', ['projects' => $projects, 'tree' => $nodesByProject]);
    }

    private function nest(array $rows): array
    {
        $byParent = [];
        foreach ($rows as $r) $byParent[$r['parent_id'] ?: 0][] = $r;
        $attach = function ($pid) use (&$attach, $byParent) {
            $out = [];
            foreach ($byParent[$pid] ?? [] as $r) {
                $r['children'] = $attach((int) $r['id']);
                $out[] = $r;
            }
            return $out;
        };
        return $attach(0);
    }

    /** دادهٔ گرید — فیلتر پروژه/بخش/وضعیت گردش کار/اولویت/نوع + جست‌وجوی همهٔ فیلدهای متنی */
    public function data()
    {
        $db = db_connect();
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $projectId = (int) ($this->request->getGet('project_id') ?? 0);
        $nodeId = (int) ($this->request->getGet('node_id') ?? 0);
        $workflow = (string) ($this->request->getGet('workflow') ?? '');
        $priority = (string) ($this->request->getGet('priority') ?? '');
        $testType = (string) ($this->request->getGet('test_type') ?? '');
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 20;

        // آخرین نتیجه/وضعیت گردش کار هر سناریو از جدیدترین دورهٔ باز
        $b = $db->table('test_cases tc')
            ->select('tc.id, tc.code, tc.title, tc.priority, tc.test_type, tc.created_at, tc.updated_at,
                      p.name project_name, p.code project_code, n.name node_name,
                      ut.display_name tester_name, ud.display_name developer_name, um.display_name manager_name,
                      rc.workflow_status,
                      (SELECT e.result FROM test_executions e WHERE e.test_run_case_id = rc.id ORDER BY e.execution_no DESC LIMIT 1) last_result')
            ->join('projects p', 'p.id = tc.project_id')
            ->join('project_nodes n', 'n.id = tc.node_id')
            ->join('node_assignments na', 'na.node_id = tc.node_id', 'left')
            ->join('users ut', 'ut.id = na.tester_id', 'left')
            ->join('users ud', 'ud.id = na.developer_id', 'left')
            ->join('users um', 'um.id = na.manager_id', 'left')
            ->join('test_run_cases rc', 'rc.test_case_id = tc.id AND rc.test_run_id = (SELECT MAX(rc2.test_run_id) FROM test_run_cases rc2 WHERE rc2.test_case_id = tc.id)', 'left')
            ->where('tc.archived_at', null);
        if ($projectId) $b->where('tc.project_id', $projectId);
        if ($nodeId) $b->whereIn('tc.node_id', $this->subtreeIds($nodeId));
        if ($workflow !== '') $b->where('rc.workflow_status', $workflow);
        if ($priority !== '') $b->where('tc.priority', $priority);
        if ($testType !== '') $b->where('tc.test_type', $testType);
        if ($q !== '') $b->groupStart()
            ->like('tc.title', $q)->orLike('tc.code', $q)->orLike('tc.objective', $q)
            ->orLike('tc.expected_result', $q)->orLike('tc.preconditions', $q)->orLike('tc.test_data', $q)
            ->groupEnd();
        $total = $b->countAllResults(false);
        $rows = $b->orderBy('tc.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return $this->response->setJSON(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage]);
    }

    /** شناسهٔ خود گره + همهٔ فرزندان (برای فیلتر درختی) */
    private function subtreeIds(int $nodeId): array
    {
        $db = db_connect();
        $ids = [$nodeId];
        $queue = [$nodeId];
        $guard = 0;
        while ($queue && $guard++ < 500) {
            $parents = array_map('intval', $queue);
            $queue = [];
            $rows = $db->table('project_nodes')->select('id')->whereIn('parent_id', $parents)->where('is_active', 1)->get()->getResultArray();
            foreach ($rows as $r) {
                $ids[] = (int) $r['id'];
                $queue[] = (int) $r['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * تاریخچهٔ کامل سناریو به‌صورت درخت — از لحظهٔ ایجاد تا اکنون.
     * دسترسی: ادمین یا هر یک از سه مسئول بخشِ سناریو.
     */
    public function timeline(int $id)
    {
        $db = db_connect();
        $tc = $db->query(
            'SELECT tc.id, tc.code, tc.title, tc.node_id, tc.created_at, tc.created_by, p.name project_name
             FROM test_cases tc JOIN projects p ON p.id = tc.project_id WHERE tc.id = ?', [$id]
        )->getFirstRow('array');
        if (!$tc) return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => t('cases.not_found')]);
        $as = $db->table('node_assignments')->where('node_id', $tc['node_id'])->get()->getFirstRow('array');
        $uid = (int) session('user_id');
        if (!session('is_admin') && !in_array($uid, [(int) ($as['tester_id'] ?? 0), (int) ($as['developer_id'] ?? 0), (int) ($as['manager_id'] ?? 0)], true))
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => t('hist.no_access')]);

        $creator = $db->table('users')->select('display_name')->where('id', $tc['created_by'])->get()->getFirstRow('array')['display_name'] ?? '—';
        $meta = WorkflowService::statusMeta();
        $resLabel = ['PASS' => t('result.PASS') . ' ✅', 'FAIL' => t('result.FAIL') . ' ❌', 'BLOCKED' => t('result.BLOCKED') . ' ⛔'];

        // رخدادهای سطح خطا (شروع اصلاح / اعلام رفع) باید زیر همان خطا قرار گیرند
        $history = $db->query(
            'SELECT wh.from_status, wh.to_status, wh.reason, wh.created_at, u.display_name actor
             FROM workflow_history wh JOIN users u ON u.id = wh.actor_user_id
             WHERE wh.test_run_case_id IN (SELECT id FROM test_run_cases WHERE test_case_id = ?)
             ORDER BY wh.id ASC', [$id]
        )->getResultArray();
        $defectEvents = [];
        $topEvents = [];
        foreach ($history as $h) {
            if (preg_match('/^(شروع اصلاح|اعلام رفع) خطای (\S+)/u', $h['reason'], $mm)) {
                $defectEvents[$mm[2]][] = [
                    'icon' => $mm[1] === 'شروع اصلاح' ? '🔧' : '✅',
                    'label' => $mm[1] === 'شروع اصلاح' ? t('hist.start_fix') : t('hist.fix_done'),
                    'reason' => '', 'actor' => $h['actor'], 'at' => $h['created_at'], 'children' => [],
                ];
                continue;
            }
            $toLabel = $meta[$h['to_status']][0] ?? $h['to_status'];
            $icons = ['READY' => '▶', 'IN_TEST' => '🎬', 'IN_FIX' => '🛠', 'READY_RETEST' => '🔄', 'BLOCKED' => '⛔', 'PENDING_APPROVAL' => '⏳', 'APPROVED' => '✅', 'REJECTED' => '❌', 'CANCELLED' => '🚫'];
            $topEvents[] = [
                'icon' => $icons[$h['to_status']] ?? '•',
                'label' => ($h['from_status'] ? ($meta[$h['from_status']][0] ?? $h['from_status']) . ' ← ' : '') . $toLabel,
                'reason' => $h['reason'], 'actor' => $h['actor'], 'at' => $h['created_at'], 'children' => [],
            ];
        }

        // اجراها + خطاهای زیرمجموعهٔ هر اجرا
        $executions = $db->query(
            'SELECT e.id, e.execution_no, e.result, e.actual_result, e.executed_at, u.display_name tester
             FROM test_executions e JOIN users u ON u.id = e.tester_id
             WHERE e.test_run_case_id IN (SELECT id FROM test_run_cases WHERE test_case_id = ?)
             ORDER BY e.id ASC', [$id]
        )->getResultArray();
        $sev = WorkflowService::severityMeta();
        foreach ($executions as $e) {
            $kids = [];
            $defects = $db->query('SELECT id, code, title, severity, status, reported_by, created_at FROM defects WHERE execution_id = ? ORDER BY id', [$e['id']])->getResultArray();
            foreach ($defects as $d) {
                $reporter = $db->table('users')->select('display_name')->where('id', $d['reported_by'])->get()->getFirstRow('array')['display_name'] ?? '—';
                $kids[] = [
                    'icon' => '🐛',
                    'label' => t('hist.defect', ['code' => $d['code'], 'title' => $d['title']]),
                    'reason' => t('hist.defect_meta', ['sev' => ($sev[$d['severity']][0] ?? $d['severity']), 'st' => (WorkflowService::defectStatusMeta()[$d['status']][0] ?? $d['status'])]),
                    'actor' => $reporter, 'at' => $d['created_at'],
                    'children' => $defectEvents[$d['code']] ?? [],
                ];
            }
            $topEvents[] = [
                'icon' => '🧪',
                'label' => t('hist.exec_no', ['n' => $e['execution_no'], 'res' => ($resLabel[$e['result']] ?? $e['result'])]),
                'reason' => $e['actual_result'], 'actor' => $e['tester'], 'at' => $e['executed_at'], 'children' => $kids,
            ];
        }

        // ویرایش‌ها (revision های بعد از ایجاد)
        $revs = $db->query(
            'SELECT r.revision_no, r.reason, r.created_at, u.display_name actor
             FROM test_case_revisions r JOIN users u ON u.id = r.created_by
             WHERE r.test_case_id = ? AND r.revision_no > 1 ORDER BY r.id', [$id]
        )->getResultArray();
        foreach ($revs as $r) {
            $topEvents[] = ['icon' => '✏️', 'label' => t('hist.edit', ['n' => $r['revision_no']]), 'reason' => $r['reason'], 'actor' => $r['actor'], 'at' => $r['created_at'], 'children' => []];
        }

        usort($topEvents, fn($a, $b) => strcmp($a['at'], $b['at']));

        $tree = [
            'icon' => '📦',
            'label' => t('hist.root', ['code' => $tc['code'], 'title' => $tc['title']]),
            'reason' => t('hist.project', ['name' => $tc['project_name']]),
            'actor' => $creator, 'at' => $tc['created_at'],
            'children' => $topEvents,
        ];
        return $this->response->setJSON(['ok' => true, 'code' => $tc['code'], 'title' => $tc['title'], 'tree' => $tree]);
    }

    /** پاپ‌آپ جزئیات فقط‌خواندنی: مشخصات، مراحل، مسئولان، اجراها، خطاها و چرخهٔ رفت‌وبرگشت */
    public function detail(int $id)
    {
        $db = db_connect();
        $tc = $db->query(
            'SELECT tc.*, p.name project_name, p.code project_code, n.name node_name,
                    ut.display_name tester_name, ud.display_name developer_name, um.display_name manager_name
             FROM test_cases tc
             JOIN projects p ON p.id = tc.project_id
             JOIN project_nodes n ON n.id = tc.node_id
             LEFT JOIN node_assignments na ON na.node_id = tc.node_id
             LEFT JOIN users ut ON ut.id = na.tester_id
             LEFT JOIN users ud ON ud.id = na.developer_id
             LEFT JOIN users um ON um.id = na.manager_id
             WHERE tc.id = ?', [$id]
        )->getFirstRow('array');
        if (!$tc) return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => t('cases.not_found')]);

        $steps = $db->table('test_case_steps')->where('test_case_id', $id)->orderBy('step_order')->get()->getResultArray();
        $executions = $db->query(
            'SELECT e.execution_no, e.result, e.actual_result, e.notes, e.executed_at, u.display_name tester_name
             FROM test_executions e JOIN users u ON u.id = e.tester_id
             WHERE e.test_run_case_id IN (SELECT id FROM test_run_cases WHERE test_case_id = ?)
             ORDER BY e.id DESC', [$id]
        )->getResultArray();
        $defects = $db->query(
            'SELECT d.id, d.code, d.title, d.severity, d.status, d.fix_notes, d.created_at
             FROM defects d WHERE d.test_run_case_id IN (SELECT id FROM test_run_cases WHERE test_case_id = ?)
             ORDER BY d.id DESC', [$id]
        )->getResultArray();
        $history = $db->query(
            'SELECT wh.from_status, wh.to_status, wh.reason, wh.created_at, u.display_name actor_name
             FROM workflow_history wh JOIN users u ON u.id = wh.actor_user_id
             WHERE wh.test_run_case_id IN (SELECT id FROM test_run_cases WHERE test_case_id = ?)
             ORDER BY wh.id DESC LIMIT 50', [$id]
        )->getResultArray();
        $revisions = $db->table('test_case_revisions')->select('revision_no, reason, created_at')->where('test_case_id', $id)->orderBy('revision_no', 'DESC')->get()->getResultArray();
        $currentStatus = $db->query(
            'SELECT rc.workflow_status FROM test_run_cases rc WHERE rc.test_case_id = ? ORDER BY rc.id DESC LIMIT 1', [$id]
        )->getRowArray()['workflow_status'] ?? null;

        $typeLabels = ['FUNCTIONAL' => t('cases.type.functional'), 'UI' => t('cases.type.ui'), 'SECURITY' => t('cases.type.security'), 'REGRESSION' => t('cases.type.regression'), 'OTHER' => t('cases.type.other')];
        return $this->response->setJSON([
            'ok' => true,
            'case' => [
                'code' => $tc['code'], 'title' => $tc['title'], 'objective' => $tc['objective'],
                'preconditions' => $tc['preconditions'], 'test_data' => $tc['test_data'], 'expected_result' => $tc['expected_result'],
                'priority' => $tc['priority'], 'priority_label' => WorkflowService::priorityMeta()[$tc['priority']][0] ?? $tc['priority'],
                'test_type' => $tc['test_type'], 'test_type_label' => $typeLabels[$tc['test_type']] ?? $tc['test_type'],
                'status' => $tc['status'], 'current_revision' => $tc['current_revision'],
                'project' => $tc['project_name'], 'node' => (new WorkflowService())->nodePath((int) $tc['node_id']),
                'tester' => $tc['tester_name'], 'developer' => $tc['developer_name'], 'manager' => $tc['manager_name'],
                'updated_at' => $tc['updated_at'],
                'workflow_status' => $currentStatus,
            ],
            'steps' => $steps, 'executions' => $executions, 'defects' => $defects, 'history' => $history, 'revisions' => $revisions,
        ]);
    }
}
