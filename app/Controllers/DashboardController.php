<?php

namespace App\Controllers;

use App\Services\WorkflowService;

/** داشبورد مدیر — آخرین وضعیت تست‌های هر پروژه + آخرین رویدادها */
class DashboardController extends BaseController
{
    public function index()
    {
        if (!session('is_admin')) return redirect()->to('/workbench');
        $db = db_connect();
        $projects = $db->query(
            'SELECT p.id, p.code, p.name, p.status,
                    (SELECT COUNT(*) FROM test_cases tc WHERE tc.project_id = p.id AND tc.archived_at IS NULL) total_cases,
                    (SELECT COUNT(*) FROM test_run_cases rc JOIN test_runs r ON r.id = rc.test_run_id
                      JOIN test_cases tc2 ON tc2.id = rc.test_case_id
                      WHERE tc2.project_id = p.id AND r.status = "OPEN" AND rc.workflow_status IN ("READY","IN_TEST","READY_RETEST")) pending_test,
                    (SELECT COUNT(*) FROM test_run_cases rc JOIN test_runs r ON r.id = rc.test_run_id
                      JOIN test_cases tc3 ON tc3.id = rc.test_case_id
                      WHERE tc3.project_id = p.id AND rc.workflow_status = "PENDING_APPROVAL") pending_approval,
                    (SELECT COUNT(*) FROM test_run_cases rc JOIN test_runs r ON r.id = rc.test_run_id
                      JOIN test_cases tc4 ON tc4.id = rc.test_case_id
                      WHERE tc4.project_id = p.id AND rc.workflow_status = "IN_FIX") in_fix,
                    (SELECT COUNT(*) FROM test_run_cases rc JOIN test_runs r ON r.id = rc.test_run_id
                      JOIN test_cases tc5 ON tc5.id = rc.test_case_id
                      WHERE tc5.project_id = p.id AND rc.workflow_status = "APPROVED") approved,
                    (SELECT COUNT(*) FROM defects d WHERE d.project_id = p.id AND d.status IN ("OPEN","IN_PROGRESS","REOPENED")) open_defects,
                    (SELECT MAX(e.executed_at) FROM test_executions e JOIN test_cases tc6 ON tc6.id = e.test_run_case_id WHERE tc6.project_id = p.id) last_execution
             FROM projects p
             WHERE p.status = "ACTIVE"
             ORDER BY p.id DESC'
        )->getResultArray();
        return view('dashboard/index', ['projects' => $projects]);
    }

    /** AJAX: آخرین اجراهای تست در کل سامانه */
    public function recent()
    {
        if (!session('is_admin')) return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        $rows = db_connect()->query(
            'SELECT e.result, e.executed_at, tc.code tc_code, tc.title, p.name project_name, u.display_name tester_name
             FROM test_executions e
             JOIN test_run_cases rc ON rc.id = e.test_run_case_id
             JOIN test_cases tc ON tc.id = rc.test_case_id
             JOIN projects p ON p.id = tc.project_id
             JOIN users u ON u.id = e.tester_id
             ORDER BY e.id DESC LIMIT 12'
        )->getResultArray();
        $resMeta = ['PASS' => t('result.PASS'), 'FAIL' => t('result.FAIL'), 'BLOCKED' => t('result.BLOCKED')];
        return $this->response->setJSON(['ok' => true, 'rows' => array_map(fn($r) => [
            'result' => $r['result'], 'result_label' => $resMeta[$r['result']] ?? $r['result'],
            'result_class' => ['PASS' => 'st-approved', 'FAIL' => 'st-infix', 'BLOCKED' => 'st-blocked'][$r['result']] ?? '',
            'code' => $r['tc_code'], 'title' => $r['title'], 'project' => $r['project_name'],
            'tester' => $r['tester_name'], 'at' => $r['executed_at'],
        ], $rows)]);
    }
}
