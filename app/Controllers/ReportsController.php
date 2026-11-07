<?php

namespace App\Controllers;

use App\Services\WorkflowService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** گزارش‌ها: خلاصهٔ پروژه + نمودار + خروجی Excel + چاپ */
class ReportsController extends BaseController
{
    public function index()
    {
        if (!session('is_admin')) return redirect()->to('/workbench');
        $db = db_connect();
        $projects = $db->query(
            'SELECT p.id, p.code, p.name FROM projects p WHERE p.status = "ACTIVE" ORDER BY p.name'
        )->getResultArray();
        return view('reports/index', ['projects' => $projects]);
    }

    /** AJAX: دادهٔ گزارش یک پروژه */
    public function data()
    {
        if (!session('is_admin')) return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        $projectId = (int) ($this->request->getGet('project_id') ?? 0);
        if (!$projectId) return $this->response->setJSON(['ok' => false, 'message' => 'پروژه انتخاب نشده است.']);
        $db = db_connect();

        // خلاصهٔ وضعیت
        $summary = $db->query(
            "SELECT
                COUNT(DISTINCT tc.id) total_cases,
                SUM(CASE WHEN rc.id IS NOT NULL THEN 1 ELSE 0 END) executed,
                SUM(CASE WHEN rc.workflow_status = 'APPROVED' THEN 1 ELSE 0 END) approved,
                SUM(CASE WHEN rc.workflow_status IN ('READY','IN_TEST','READY_RETEST','BLOCKED') THEN 1 ELSE 0 END) in_progress,
                SUM(CASE WHEN rc.workflow_status = 'IN_FIX' THEN 1 ELSE 0 END) in_fix,
                SUM(CASE WHEN rc.workflow_status = 'PENDING_APPROVAL' THEN 1 ELSE 0 END) pending_approval,
                SUM(CASE WHEN rc.workflow_status IN ('REJECTED','CANCELLED') THEN 1 ELSE 0 END) closed,
                (SELECT COUNT(*) FROM defects d WHERE d.project_id = tc.project_id AND d.status IN ('OPEN','IN_PROGRESS','REOPENED')) open_defects
             FROM test_cases tc
             LEFT JOIN test_run_cases rc ON rc.test_case_id = tc.id
             WHERE tc.project_id = ? AND tc.archived_at IS NULL",
            [$projectId]
        )->getFirstRow('array');

        // آخرین نتایج
        $passCount = (int) $db->query(
            "SELECT COUNT(*) n FROM test_run_cases rc
             JOIN test_cases tc ON tc.id = rc.test_case_id
             LEFT JOIN test_executions e ON e.test_run_case_id = rc.id AND e.execution_no = (
                 SELECT MAX(e2.execution_no) FROM test_executions e2 WHERE e2.test_run_case_id = rc.id
             )
             WHERE tc.project_id = ? AND e.result = 'PASS'",
            [$projectId]
        )->getRowArray()['n'];
        $failCount = (int) $db->query(
            "SELECT COUNT(*) n FROM test_run_cases rc
             JOIN test_cases tc ON tc.id = rc.test_case_id
             LEFT JOIN test_executions e ON e.test_run_case_id = rc.id AND e.execution_no = (
                 SELECT MAX(e2.execution_no) FROM test_executions e2 WHERE e2.test_run_case_id = rc.id
             )
             WHERE tc.project_id = ? AND e.result = 'FAIL'",
            [$projectId]
        )->getRowArray()['n'];

        $executed = (int) ($summary['executed'] ?? 0);
        $summary['pass_count'] = $passCount;
        $summary['fail_count'] = $failCount;
        $summary['pass_rate'] = $executed > 0 ? round($passCount / $executed * 100) : 0;
        $summary['coverage'] = ((int) ($summary['total_cases'] ?? 0)) > 0
            ? round($executed / (int) $summary['total_cases'] * 100) : 0;

        // آخرین اجراها
        $recent = $db->query(
            "SELECT tc.code, tc.title, e.result, e.executed_at, u.display_name tester
             FROM test_executions e
             JOIN test_run_cases rc ON rc.id = e.test_run_case_id
             JOIN test_cases tc ON tc.id = rc.test_case_id
             JOIN users u ON u.id = e.tester_id
             WHERE tc.project_id = ?
             ORDER BY e.id DESC LIMIT 15",
            [$projectId]
        )->getResultArray();

        // خطاهای باز
        $defects = $db->query(
            "SELECT d.code, d.title, d.severity, d.status, d.created_at,
                    u.display_name developer_name
             FROM defects d
             LEFT JOIN users u ON u.id = d.developer_id
             WHERE d.project_id = ? AND d.status IN ('OPEN','IN_PROGRESS','REOPENED','FIXED_PENDING_RETEST')
             ORDER BY FIELD(d.severity,'CRITICAL','HIGH','MEDIUM','LOW'), d.id DESC LIMIT 30",
            [$projectId]
        )->getResultArray();

        // وضعیت به تفکیک بخش
        $byNode = $db->query(
            "SELECT n.name node_name, COUNT(tc.id) total,
                    SUM(CASE WHEN rc.workflow_status = 'APPROVED' THEN 1 ELSE 0 END) approved,
                    SUM(CASE WHEN rc.workflow_status IN ('IN_FIX') THEN 1 ELSE 0 END) in_fix,
                    SUM(CASE WHEN rc.workflow_status IN ('READY','IN_TEST','READY_RETEST','BLOCKED','PENDING_APPROVAL') THEN 1 ELSE 0 END) active
             FROM project_nodes n
             LEFT JOIN test_cases tc ON tc.node_id = n.id AND tc.archived_at IS NULL
             LEFT JOIN test_run_cases rc ON rc.test_case_id = tc.id
             WHERE n.project_id = ? AND n.is_active = 1
             GROUP BY n.id, n.name
             HAVING total > 0
             ORDER BY n.name",
            [$projectId]
        )->getResultArray();

        return $this->response->setJSON([
            'ok' => true, 'summary' => $summary, 'recent' => $recent,
            'defects' => $defects, 'byNode' => $byNode,
        ]);
    }

    /** خروجی Excel کامل */
    public function excel()
    {
        if (!session('is_admin')) return redirect()->to('/workbench');
        $projectId = (int) ($this->request->getGet('project_id') ?? 0);
        if (!$projectId) return redirect()->to('/reports');
        $db = db_connect();
        $proj = $db->table('projects')->where('id', $projectId)->get()->getFirstRow('array');
        if (!$proj) return redirect()->to('/reports');

        $ss = new Spreadsheet();
        // ── شیت ۱: خلاصه ──
        $sh = $ss->getActiveSheet();
        $sh->setTitle('خلاصه');
        $sh->setCellValue('A1', 'گزارش تست: ' . $proj['name']);
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sh->setCellValue('A2', 'تاریخ: ' . gmdate('Y-m-d H:i') . ' UTC');
        $summary = [
            ['شاخص', 'مقدار'],
            ['کل سناریوها', $db->query("SELECT COUNT(*) n FROM test_cases WHERE project_id=? AND archived_at IS NULL", [$projectId])->getRowArray()['n']],
            ['اجرا شده', $db->query("SELECT COUNT(DISTINCT rc.test_case_id) n FROM test_run_cases rc JOIN test_cases tc ON tc.id=rc.test_case_id WHERE tc.project_id=?", [$projectId])->getRowArray()['n']],
            ['تأیید شده', $db->query("SELECT COUNT(*) n FROM test_run_cases rc JOIN test_cases tc ON tc.id=rc.test_case_id WHERE tc.project_id=? AND rc.workflow_status='APPROVED'", [$projectId])->getRowArray()['n']],
            ['در حال اصلاح', $db->query("SELECT COUNT(*) n FROM test_run_cases rc JOIN test_cases tc ON tc.id=rc.test_case_id WHERE tc.project_id=? AND rc.workflow_status='IN_FIX'", [$projectId])->getRowArray()['n']],
            ['خطاهای باز', $db->query("SELECT COUNT(*) n FROM defects WHERE project_id=? AND status IN ('OPEN','IN_PROGRESS','REOPENED')", [$projectId])->getRowArray()['n']],
        ];
        $sh->fromArray($summary, null, 'A4');
        foreach (range('A', 'B') as $col) $sh->getColumnDimension($col)->setAutoSize(true);

        // ── شیت ۲: سناریوها ──
        $sh2 = $ss->createSheet();
        $sh2->setTitle('سناریوها');
        $headers = ['کد', 'عنوان', 'بخش', 'اولویت', 'وضعیت گردش کار', 'آخرین نتیجه', 'آزمونگر', 'برنامه‌نویس', 'مدیر'];
        foreach ($headers as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sh2->setCellValue($col . '1', $h);
            $sh2->getStyle($col . '1')->getFont()->setBold(true);
            $sh2->getColumnDimension($col)->setAutoSize(true);
        }
        $cases = $db->query(
            "SELECT tc.code, tc.title, n.name node_name, tc.priority, rc.workflow_status,
                    (SELECT e.result FROM test_executions e WHERE e.test_run_case_id = rc.id ORDER BY e.execution_no DESC LIMIT 1) last_result,
                    ut.display_name tester, ud.display_name developer, um.display_name manager
             FROM test_cases tc
             JOIN project_nodes n ON n.id = tc.node_id
             LEFT JOIN test_run_cases rc ON rc.test_case_id = tc.id
             LEFT JOIN node_assignments na ON na.node_id = tc.node_id
             LEFT JOIN users ut ON ut.id = na.tester_id
             LEFT JOIN users ud ON ud.id = na.developer_id
             LEFT JOIN users um ON um.id = na.manager_id
             WHERE tc.project_id = ? AND tc.archived_at IS NULL
             ORDER BY tc.code",
            [$projectId]
        )->getResultArray();
        $statusMeta = WorkflowService::statusMeta();
        $resMeta = ['PASS' => 'موفق', 'FAIL' => 'ناموفق', 'BLOCKED' => 'مسدود'];
        $row = 2;
        foreach ($cases as $c) {
            $sh2->setCellValue("A$row", $c['code']);
            $sh2->setCellValue("B$row", $c['title']);
            $sh2->setCellValue("C$row", $c['node_name']);
            $sh2->setCellValue("D$row", WorkflowService::priorityMeta()[$c['priority']][0] ?? $c['priority']);
            $sh2->setCellValue("E$row", $statusMeta[$c['workflow_status']][0] ?? '—');
            $sh2->setCellValue("F$row", $resMeta[$c['last_result']] ?? 'بدون اجرا');
            $sh2->setCellValue("G$row", $c['tester'] ?? '—');
            $sh2->setCellValue("H$row", $c['developer'] ?? '—');
            $sh2->setCellValue("I$row", $c['manager'] ?? '—');
            $row++;
        }

        // ── شیت ۳: خطاها ──
        $sh3 = $ss->createSheet();
        $sh3->setTitle('خطاها');
        $h3 = ['کد', 'عنوان', 'شدت', 'وضعیت', 'برنامه‌نویس', 'تاریخ ثبت'];
        foreach ($h3 as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sh3->setCellValue($col . '1', $h);
            $sh3->getStyle($col . '1')->getFont()->setBold(true);
            $sh3->getColumnDimension($col)->setAutoSize(true);
        }
        $defects = $db->query(
            "SELECT d.code, d.title, d.severity, d.status, u.display_name dev, d.created_at
             FROM defects d LEFT JOIN users u ON u.id = d.developer_id
             WHERE d.project_id = ? ORDER BY d.id DESC",
            [$projectId]
        )->getResultArray();
        $dStatMeta = WorkflowService::defectStatusMeta();
        $row = 2;
        foreach ($defects as $d) {
            $sh3->setCellValue("A$row", $d['code']);
            $sh3->setCellValue("B$row", $d['title']);
            $sh3->setCellValue("C$row", WorkflowService::severityMeta()[$d['severity']][0] ?? $d['severity']);
            $sh3->setCellValue("D$row", $dStatMeta[$d['status']][0] ?? $d['status']);
            $sh3->setCellValue("E$row", $d['dev'] ?? '—');
            $sh3->setCellValue("F$row", $d['created_at']);
            $row++;
        }

        $file = tempnam(sys_get_temp_dir(), 'rep') . '.xlsx';
        (new Xlsx($ss))->save($file);
        return $this->response->download("report-{$proj['code']}.xlsx", file_get_contents($file))
            ->setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** نمای چاپ */
    public function printView()
    {
        if (!session('is_admin')) return redirect()->to('/workbench');
        $projectId = (int) ($this->request->getGet('project_id') ?? 0);
        if (!$projectId) return redirect()->to('/reports');
        $db = db_connect();
        $proj = $db->table('projects')->where('id', $projectId)->get()->getFirstRow('array');
        if (!$proj) return redirect()->to('/reports');

        $summary = $db->query(
            "SELECT
                COUNT(DISTINCT tc.id) total_cases,
                SUM(CASE WHEN rc.workflow_status = 'APPROVED' THEN 1 ELSE 0 END) approved,
                SUM(CASE WHEN rc.workflow_status = 'IN_FIX' THEN 1 ELSE 0 END) in_fix,
                SUM(CASE WHEN rc.workflow_status IN ('READY','IN_TEST','READY_RETEST','BLOCKED','PENDING_APPROVAL') THEN 1 ELSE 0 END) active,
                (SELECT COUNT(*) FROM defects d WHERE d.project_id = ? AND d.status IN ('OPEN','IN_PROGRESS','REOPENED')) open_defects
             FROM test_cases tc
             LEFT JOIN test_run_cases rc ON rc.test_case_id = tc.id
             WHERE tc.project_id = ? AND tc.archived_at IS NULL",
            [$projectId, $projectId]
        )->getFirstRow('array');

        $cases = $db->query(
            "SELECT tc.code, tc.title, tc.priority, rc.workflow_status,
                    (SELECT e.result FROM test_executions e WHERE e.test_run_case_id = rc.id ORDER BY e.execution_no DESC LIMIT 1) last_result
             FROM test_cases tc
             LEFT JOIN test_run_cases rc ON rc.test_case_id = tc.id
             WHERE tc.project_id = ? AND tc.archived_at IS NULL
             ORDER BY tc.code",
            [$projectId]
        )->getResultArray();

        $defects = $db->query(
            "SELECT d.code, d.title, d.severity, d.status
             FROM defects d WHERE d.project_id = ?
             ORDER BY FIELD(d.severity,'CRITICAL','HIGH','MEDIUM','LOW'), d.id DESC LIMIT 20",
            [$projectId]
        )->getResultArray();

        return view('reports/print', [
            'project' => $proj, 'summary' => $summary,
            'cases' => $cases, 'defects' => $defects,
        ]);
    }
}
