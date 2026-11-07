<?php

namespace App\Controllers;

/** داشبورد مدیر — KPI + آخرین فعالیت‌ها + میانبرهای سریع */
class DashboardController extends BaseController
{
    public function index()
    {
        if (!session('is_admin')) return redirect()->to('/workbench');
        $db = db_connect();

        // KPI
        $kpi = $db->query(
            'SELECT
                (SELECT COUNT(*) FROM projects WHERE status = "ACTIVE") projects,
                (SELECT COUNT(*) FROM test_cases WHERE archived_at IS NULL) total_cases,
                (SELECT COUNT(*) FROM test_run_cases rc JOIN test_cases tc ON tc.id = rc.test_case_id WHERE tc.archived_at IS NULL AND rc.workflow_status IN ("READY","IN_TEST","READY_RETEST")) pending_test,
                (SELECT COUNT(*) FROM test_run_cases rc JOIN test_cases tc ON tc.id = rc.test_case_id WHERE tc.archived_at IS NULL AND rc.workflow_status = "APPROVED") approved,
                (SELECT COUNT(*) FROM test_run_cases rc JOIN test_cases tc ON tc.id = rc.test_case_id WHERE tc.archived_at IS NULL AND rc.workflow_status = "PENDING_APPROVAL") pending_approval,
                (SELECT COUNT(*) FROM defects WHERE status IN ("OPEN","IN_PROGRESS","REOPENED")) open_defects,
                (SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL) users'
        )->getFirstRow('array');

        // آخرین فعالیت‌ها
        $activity = $db->query(
            'SELECT wh.from_status, wh.to_status, wh.reason, wh.created_at, u.display_name actor,
                    tc.code, tc.title, p.name project_name
             FROM workflow_history wh
             JOIN users u ON u.id = wh.actor_user_id
             JOIN test_run_cases rc ON rc.id = wh.test_run_case_id
             JOIN test_cases tc ON tc.id = rc.test_case_id
             JOIN projects p ON p.id = tc.project_id
             ORDER BY wh.id DESC LIMIT 10'
        )->getResultArray();

        // خطاهای باز
        $recentDefects = $db->query(
            'SELECT d.code, d.title, d.severity, d.status, p.name project_name
             FROM defects d JOIN projects p ON p.id = d.project_id
             WHERE d.status IN ("OPEN","IN_PROGRESS","REOPENED")
             ORDER BY FIELD(d.severity,"CRITICAL","HIGH","MEDIUM","LOW"), d.id DESC LIMIT 5'
        )->getResultArray();

        // کارت‌های پروژه
        $projects = $db->query(
            'SELECT p.id, p.code, p.name,
                    (SELECT COUNT(*) FROM test_cases tc WHERE tc.project_id = p.id AND tc.archived_at IS NULL) total_cases,
                    (SELECT COUNT(*) FROM test_run_cases rc JOIN test_cases tc ON tc.id = rc.test_case_id WHERE tc.project_id = p.id AND rc.workflow_status = "APPROVED") approved,
                    (SELECT COUNT(*) FROM defects d WHERE d.project_id = p.id AND d.status IN ("OPEN","IN_PROGRESS","REOPENED")) open_defects
             FROM projects p WHERE p.status = "ACTIVE" ORDER BY p.id DESC'
        )->getResultArray();

        return view('dashboard/index', [
            'kpi' => $kpi, 'activity' => $activity, 'recentDefects' => $recentDefects, 'projects' => $projects,
        ]);
    }

    /** AJAX: آخرین اجراها */
    public function recent()
    {
        if (!session('is_admin')) return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        $rows = db_connect()->query(
            'SELECT e.result, e.executed_at, tc.code, tc.title, p.name project_name, u.display_name tester_name
             FROM test_executions e
             JOIN test_run_cases rc ON rc.id = e.test_run_case_id
             JOIN test_cases tc ON tc.id = rc.test_case_id
             JOIN projects p ON p.id = tc.project_id
             JOIN users u ON u.id = e.tester_id
             ORDER BY e.id DESC LIMIT 10'
        )->getResultArray();
        $resMeta = ['PASS' => 'موفق', 'FAIL' => 'ناموفق', 'BLOCKED' => 'مسدود'];
        return $this->response->setJSON(['ok' => true, 'rows' => array_map(fn($r) => [
            'result' => $r['result'], 'result_label' => $resMeta[$r['result']] ?? $r['result'],
            'result_class' => ['PASS' => 'st-approved', 'FAIL' => 'st-infix', 'BLOCKED' => 'st-blocked'][$r['result']] ?? '',
            'code' => $r['code'], 'title' => $r['title'], 'project' => $r['project_name'],
            'tester' => $r['tester_name'], 'at' => $r['executed_at'],
        ], $rows)]);
    }
}
