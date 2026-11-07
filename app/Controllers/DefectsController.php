<?php

namespace App\Controllers;

use App\Services\WorkflowService;

/** فهرست خطاها — همهٔ خطاها در یک صفحه با فیلتر */
class DefectsController extends BaseController
{
    public function index()
    {
        if (!session('is_admin')) return redirect()->to('/workbench');
        return view('defects/index');
    }

    public function data()
    {
        if (!session('is_admin')) return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        $db = db_connect();
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $status = (string) ($this->request->getGet('status') ?? '');
        $severity = (string) ($this->request->getGet('severity') ?? '');
        $projectId = (int) ($this->request->getGet('project_id') ?? 0);
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 20;

        $b = $db->table('defects d')
            ->select('d.id, d.code, d.title, d.description, d.severity, d.status, d.fix_notes, d.created_at, d.updated_at,
                      p.name project_name, p.code project_code, tc.code tc_code, tc.title tc_title, n.name node_name,
                      u.display_name developer_name, ur.display_name reporter_name')
            ->join('projects p', 'p.id = d.project_id')
            ->join('test_run_cases rc', 'rc.id = d.test_run_case_id')
            ->join('test_cases tc', 'tc.id = rc.test_case_id')
            ->join('project_nodes n', 'n.id = tc.node_id', 'left')
            ->join('users u', 'u.id = d.developer_id', 'left')
            ->join('users ur', 'ur.id = d.reported_by', 'left');
        if ($q !== '') $b->groupStart()->like('d.title', $q)->orLike('d.code', $q)->orLike('d.description', $q)->orLike('tc.title', $q)->groupEnd();
        if ($status !== '') $b->where('d.status', $status);
        if ($severity !== '') $b->where('d.severity', $severity);
        if ($projectId) $b->where('d.project_id', $projectId);
        $total = $b->countAllResults(false);
        $rows = $b->orderBy('d.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return $this->response->setJSON(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage]);
    }

    /** جزئیات خطا + کامنت‌ها */
    public function detail(int $id)
    {
        $db = db_connect();
        $d = $db->query(
            'SELECT d.*, p.name project_name, tc.code tc_code, tc.title tc_title,
                    u.display_name developer_name, ur.display_name reporter_name
             FROM defects d
             JOIN projects p ON p.id = d.project_id
             JOIN test_run_cases rc ON rc.id = d.test_run_case_id
             JOIN test_cases tc ON tc.id = rc.test_case_id
             LEFT JOIN users u ON u.id = d.developer_id
             LEFT JOIN users ur ON ur.id = d.reported_by
             WHERE d.id = ?', [$id]
        )->getFirstRow('array');
        if (!$d) return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'خطا یافت نشد.']);
        $comments = $db->query(
            'SELECT c.body, c.created_at, u.display_name author FROM defect_comments c JOIN users u ON u.id = c.author_id WHERE c.defect_id = ? ORDER BY c.id ASC', [$id]
        )->getResultArray();
        return $this->response->setJSON(['ok' => true, 'defect' => $d, 'comments' => $comments]);
    }

    public function comment(int $id)
    {
        $body = trim((string) $this->request->getPost('body'));
        if (mb_strlen($body) < 2) return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'متن کامنت حداقل ۲ نویسه باشد.']);
        $db = db_connect();
        if (!$db->table('defects')->where('id', $id)->countAllResults()) return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'خطا یافت نشد.']);
        $db->table('defect_comments')->insert(['defect_id' => $id, 'author_id' => session('user_id'), 'body' => $body, 'created_at' => gmdate('Y-m-d H:i:s')]);
        return $this->response->setJSON(['ok' => true, 'message' => 'کامنت ثبت شد.']);
    }
}
