<?php

namespace App\Controllers;

use App\Services\WorkflowService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** خروجی Excel برای سناریوها و خطاها */
class ExportController extends BaseController
{
    /** خروجی Excel سناریوهای یک بخش (برای Import مجدد) */
    public function cases(int $nodeId)
    {
        $db = db_connect();
        $node = $db->table('project_nodes n')
            ->select('n.name, p.code project_code, p.id project_id')
            ->join('projects p', 'p.id = n.project_id')
            ->where('n.id', $nodeId)->get()->getFirstRow('array');
        if (!$node) return redirect()->to('/projects');

        $cases = $db->query(
            'SELECT tc.id, tc.code, tc.title, tc.objective, tc.preconditions, tc.priority, tc.test_type,
                    tc.expected_result, tc.test_data
             FROM test_cases tc
             WHERE tc.node_id = ? AND tc.archived_at IS NULL
             ORDER BY tc.code',
            [$nodeId]
        )->getResultArray();

        // مراحل هر سناریو جداگانه (ساده‌تر و مطمئن‌تر از subquery)
        $allSteps = $db->query(
            'SELECT s.test_case_id, s.instruction, s.expected_result
             FROM test_case_steps s
             JOIN test_cases tc ON tc.id = s.test_case_id
             WHERE tc.node_id = ?
             ORDER BY s.test_case_id, s.step_order',
            [$nodeId]
        )->getResultArray();
        $stepsByCase = [];
        foreach ($allSteps as $s) {
            $line = $s['expected_result'] ? $s['instruction'] . '|' . $s['expected_result'] : $s['instruction'];
            $stepsByCase[$s['test_case_id']][] = $line;
        }
        foreach ($cases as &$c) {
            $c['steps'] = isset($stepsByCase[$c['id']])
                ? implode("\n", $stepsByCase[$c['id']])
                : '';
        }

        $ss = new Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('TestCases');
        $headers = ['کد', 'عنوان', 'هدف', 'پیش‌نیازها', 'مراحل', 'داده تست', 'نتیجه مورد انتظار', 'اولویت'];
        foreach ($headers as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sh->setCellValue($col . '1', $h);
            $sh->getStyle($col . '1')->getFont()->setBold(true);
            $sh->getColumnDimension($col)->setAutoSize(true);
        }
        $row = 2;
        foreach ($cases as $c) {
            $sh->setCellValue("A$row", $c['code']);
            $sh->setCellValue("B$row", $c['title']);
            $sh->setCellValue("C$row", $c['objective']);
            $sh->setCellValue("D$row", $c['preconditions']);
            $sh->setCellValue("E$row", $c['steps']);
            $sh->setCellValue("F$row", $c['test_data']);
            $sh->setCellValue("G$row", $c['expected_result']);
            $sh->setCellValue("H$row", $c['priority']);
            $row++;
        }
        $file = tempnam(sys_get_temp_dir(), 'exp') . '.xlsx';
        (new Xlsx($ss))->save($file);
        return $this->response->download("cases-{$node['project_code']}-{$node['name']}.xlsx", file_get_contents($file))
            ->setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** خروجی Excel همهٔ خطاهای باز */
    public function defects()
    {
        $db = db_connect();
        $defects = $db->query(
            'SELECT d.code, d.title, d.description, d.severity, d.status, d.fix_notes, d.created_at,
                    p.name project_name, tc.code tc_code, u.display_name developer
             FROM defects d
             JOIN projects p ON p.id = d.project_id
             JOIN test_run_cases rc ON rc.id = d.test_run_case_id
             JOIN test_cases tc ON tc.id = rc.test_case_id
             LEFT JOIN users u ON u.id = d.developer_id
             ORDER BY d.id DESC'
        )->getResultArray();

        $ss = new Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Defects');
        $headers = ['کد', 'عنوان', 'پروژه', 'سناریو', 'شدت', 'وضعیت', 'برنامه‌نویس', 'توضیح', 'یادداشت رفع', 'تاریخ'];
        foreach ($headers as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sh->setCellValue($col . '1', $h);
            $sh->getStyle($col . '1')->getFont()->setBold(true);
            $sh->getColumnDimension($col)->setAutoSize(true);
        }
        $sevM = WorkflowService::severityMeta();
        $stM = WorkflowService::defectStatusMeta();
        $row = 2;
        foreach ($defects as $d) {
            $sh->setCellValue("A$row", $d['code']);
            $sh->setCellValue("B$row", $d['title']);
            $sh->setCellValue("C$row", $d['project_name']);
            $sh->setCellValue("D$row", $d['tc_code']);
            $sh->setCellValue("E$row", $sevM[$d['severity']][0] ?? $d['severity']);
            $sh->setCellValue("F$row", $stM[$d['status']][0] ?? $d['status']);
            $sh->setCellValue("G$row", $d['developer']);
            $sh->setCellValue("H$row", $d['description']);
            $sh->setCellValue("I$row", $d['fix_notes']);
            $sh->setCellValue("J$row", $d['created_at']);
            $row++;
        }
        $file = tempnam(sys_get_temp_dir(), 'def') . '.xlsx';
        (new Xlsx($ss))->save($file);
        return $this->response->download('defects.xlsx', file_get_contents($file))
            ->setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
