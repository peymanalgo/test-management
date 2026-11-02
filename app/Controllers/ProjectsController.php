<?php

namespace App\Controllers;

use App\Services\WorkflowService;

/** مدیریت پروژه‌ها: کارت‌ها، درخت ماژول‌ها، انتساب سه مسئول، سناریوهای هر بخش و Import اکسل */
class ProjectsController extends BaseController
{
    public function index()
    {
        $db = db_connect();
        if (strtolower($this->request->getMethod()) === 'post') {
            $data = [
                'code' => strtoupper(trim((string) $this->request->getPost('code'))),
                'name' => trim((string) $this->request->getPost('name')),
                'description' => trim((string) $this->request->getPost('description')),
                'target_url' => trim((string) $this->request->getPost('target_url')) ?: null,
                'status' => 'ACTIVE', 'created_by' => session('user_id'),
                'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
            ];
            if (!$data['code'] || !$data['name'] || mb_strlen($data['code']) > 50)
                return redirect()->back()->withInput()->with('error', t('proj.err_required'));
            if ($db->table('projects')->where('code', $data['code'])->countAllResults())
                return redirect()->back()->withInput()->with('error', t('proj.err_dup_code'));
            $db->table('projects')->insert($data);
            return redirect()->to('/projects')->with('message', t('proj.created_msg', ['name' => $data['name']]));
        }
        $projects = $db->query(
            'SELECT p.*, (SELECT COUNT(*) FROM project_nodes n WHERE n.project_id = p.id AND n.is_active = 1) node_count,
                    (SELECT COUNT(*) FROM test_cases tc WHERE tc.project_id = p.id AND tc.archived_at IS NULL) case_count
             FROM projects p WHERE p.status = "ACTIVE" ORDER BY p.id DESC'
        )->getResultArray();
        return view('projects/index', ['projects' => $projects]);
    }

    /** ویرایش پروژه (نام، کد، توضیحات، آدرس هدف) */
    public function update(int $id)
    {
        $db = db_connect();
        $proj = $db->table('projects')->where('id', $id)->where('status', 'ACTIVE')->get()->getFirstRow('array');
        if (!$proj) return $this->jsonError(t('proj.not_found'));
        $name = trim((string) $this->request->getPost('name'));
        $code = strtoupper(trim((string) $this->request->getPost('code')));
        $desc = trim((string) $this->request->getPost('description'));
        $url = trim((string) $this->request->getPost('target_url'));
        if (mb_strlen($name) < 2) return $this->jsonError(t('proj.name_min'));
        if ($code === '' || mb_strlen($code) > 50) return $this->jsonError(t('proj.code_invalid'));
        if ($code !== $proj['code'] && $db->table('projects')->where('code', $code)->countAllResults())
            return $this->jsonError('این کد پروژه قبلاً ثبت شده است.');
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) return $this->jsonError(t('proj.url_invalid'));
        $db->table('projects')->update([
            'name' => $name, 'code' => $code, 'description' => $desc ?: null,
            'target_url' => $url ?: null, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]);
        return $this->response->setJSON(['ok' => true, 'message' => t('proj.updated_msg')]);
    }

    /** صفحهٔ درخت ماژول‌های پروژه */
    public function tree(int $id)
    {
        $db = db_connect();
        $project = $db->table('projects')->where('id', $id)->where('status', 'ACTIVE')->get()->getFirstRow('array');
        if (!$project) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $nodes = $db->query(
            'SELECT n.*, ut.display_name tester_name, ud.display_name developer_name, um.display_name manager_name,
                    (SELECT COUNT(*) FROM test_cases tc WHERE tc.node_id = n.id AND tc.archived_at IS NULL) case_count
             FROM project_nodes n
             LEFT JOIN node_assignments na ON na.node_id = n.id
             LEFT JOIN users ut ON ut.id = na.tester_id
             LEFT JOIN users ud ON ud.id = na.developer_id
             LEFT JOIN users um ON um.id = na.manager_id
             WHERE n.project_id = ? AND n.is_active = 1
             ORDER BY n.sort_order, n.id', [$id]
        )->getResultArray();
        $users = $db->query(
            'SELECT u.id, u.display_name, GROUP_CONCAT(r.code) roles FROM users u
             JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
             WHERE u.is_active = 1 AND u.deleted_at IS NULL GROUP BY u.id, u.display_name ORDER BY u.display_name'
        )->getResultArray();
        return view('projects/tree', ['project' => $project, 'nodes' => $this->buildTree($nodes), 'flat' => $nodes, 'users' => $users]);
    }

    private function buildTree(array $nodes): array
    {
        $byParent = [];
        foreach ($nodes as $n) $byParent[$n['parent_id'] ?: 0][] = $n;
        $attach = function ($parentId) use (&$attach, $byParent) {
            $out = [];
            foreach ($byParent[$parentId] ?? [] as $n) {
                $n['children'] = $attach((int) $n['id']);
                $out[] = $n;
            }
            return $out;
        };
        return $attach(0);
    }

    /** ایجاد/ویرایش گره درخت (بدون حلقه، حداکثر عمق ۶ سطح) */
    public function saveNode(int $projectId)
    {
        $id = (int) ($this->request->getPost('id') ?? 0);
        $name = trim((string) $this->request->getPost('name'));
        $parentId = (int) ($this->request->getPost('parent_id') ?? 0) ?: null;
        if (mb_strlen($name) < 2) return $this->jsonError(t('tree.node_name_min'));
        $db = db_connect();
        if ($db->table('projects')->where('id', $projectId)->countAllResults() === 0) return $this->jsonError(t('proj.not_found'));
        if ($parentId) {
            $parent = $db->table('project_nodes')->where('id', $parentId)->where('project_id', $projectId)->where('is_active', 1)->get()->getFirstRow('array');
            if (!$parent) return $this->jsonError(t('tree.parent_invalid'));
            if ($id && (int) $parent['id'] === $id) return $this->jsonError('امکان انتقال یک بخش به زیرمجموعهٔ خودش وجود ندارد.');
            $cursor = $parent;
            $depth = 1;
            $guard = 0;
            while ($cursor && $cursor['parent_id'] && $guard++ < 64) {
                if ($id && (int) $cursor['parent_id'] === $id) return $this->jsonError('امکان انتقال یک بخش به زیرمجموعهٔ خودش وجود ندارد.');
                $cursor = $db->table('project_nodes')->where('id', $cursor['parent_id'])->get()->getFirstRow('array');
                $depth++;
            }
            if ($depth >= 6) return $this->jsonError(t('tree.depth_max'));
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($id) {
            $db->table('project_nodes')->update(['name' => $name, 'parent_id' => $parentId, 'updated_at' => $now], ['id' => $id, 'project_id' => $projectId]);
        } else {
            $db->table('project_nodes')->insert([
                'project_id' => $projectId, 'parent_id' => $parentId, 'name' => $name,
                'description' => trim((string) $this->request->getPost('description')) ?: null,
                'sort_order' => (int) ($this->request->getPost('sort_order') ?? 0), 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        return $this->response->setJSON(['ok' => true, 'message' => $id ? t('tree.node_saved') : 'بخش جدید به درخت اضافه شد.']);
    }

    public function deleteNode(int $id)
    {
        $db = db_connect();
        $node = $db->table('project_nodes')->where('id', $id)->where('is_active', 1)->get()->getFirstRow('array');
        if (!$node) return $this->jsonError(t('tree.node_not_found'));
        if ($db->table('project_nodes')->where('parent_id', $id)->where('is_active', 1)->countAllResults())
            return $this->jsonError(t('tree.node_has_children'));
        if ($db->table('test_cases')->where('node_id', $id)->where('archived_at', null)->countAllResults())
            return $this->jsonError(t('tree.node_has_cases'));
        $db->table('project_nodes')->update(['is_active' => 0, 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]);
        $db->table('node_assignments')->where('node_id', $id)->delete();
        return $this->response->setJSON(['ok' => true, 'message' => t('tree.node_deleted')]);
    }

    /** انتساب سه مسئول به بخش + فعال‌سازی خودکار سناریوهای پیش‌نویس */
    public function assignNode(int $nodeId)
    {
        $testerId = (int) ($this->request->getPost('tester_id') ?? 0) ?: null;
        $developerId = (int) ($this->request->getPost('developer_id') ?? 0) ?: null;
        $managerId = (int) ($this->request->getPost('manager_id') ?? 0) ?: null;
        $db = db_connect();
        $check = fn(int $uid, string $role) => $db->query(
            'SELECT COUNT(*) n FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
             WHERE u.id = ? AND u.is_active = 1 AND u.deleted_at IS NULL AND r.code = ?', [$uid, $role]
        )->getRowArray()['n'] > 0;
        if ($testerId && !$check($testerId, 'TESTER')) return $this->jsonError(t('tree.err_no_role', ['role' => t('role.tester')]));
        if ($developerId && !$check($developerId, 'DEVELOPER')) return $this->jsonError(t('tree.err_no_role', ['role' => t('role.developer')]));
        if ($managerId && !$check($managerId, 'MANAGER')) return $this->jsonError(t('tree.err_no_role', ['role' => t('role.manager')]));
        if ($testerId && $developerId && $testerId === $developerId) return $this->jsonError(t('tree.err_same_td'));
        $now = gmdate('Y-m-d H:i:s');
        $exists = $db->table('node_assignments')->where('node_id', $nodeId)->countAllResults();
        if ($exists) {
            $db->table('node_assignments')->update(['tester_id' => $testerId, 'developer_id' => $developerId, 'manager_id' => $managerId, 'updated_at' => $now], ['node_id' => $nodeId]);
        } else {
            $db->table('node_assignments')->insert(['node_id' => $nodeId, 'tester_id' => $testerId, 'developer_id' => $developerId, 'manager_id' => $managerId, 'assigned_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now]);
        }
        return $this->response->setJSON(['ok' => true, 'message' => t('tree.assign_saved')]);
    }

    /** گرید سناریوهای یک بخش — جست‌وجو/فیلتر/صفحه‌بندی سمت سرور + وضعیت گردش کار */
    public function nodeCases(int $nodeId)
    {
        $db = db_connect();
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $status = (string) ($this->request->getGet('status') ?? '');
        $priority = (string) ($this->request->getGet('priority') ?? '');
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 20;
        $b = $db->table('test_cases tc')
            ->select('tc.id, tc.code, tc.title, tc.priority, tc.created_at, tc.updated_at, rc.workflow_status,
                      (rc.workflow_status IS NULL OR rc.workflow_status = "READY") editable,
                      (SELECT e.result FROM test_executions e WHERE e.test_run_case_id = rc.id ORDER BY e.execution_no DESC LIMIT 1) last_result')
            ->join('test_run_cases rc', 'rc.test_case_id = tc.id AND rc.test_run_id = (SELECT MAX(rc2.test_run_id) FROM test_run_cases rc2 WHERE rc2.test_case_id = tc.id)', 'left')
            ->where('tc.node_id', $nodeId)->where('tc.archived_at', null);
        if ($q !== '') $b->groupStart()->like('tc.title', $q)->orLike('tc.code', $q)->orLike('tc.objective', $q)->orLike('tc.expected_result', $q)->groupEnd();
        if ($status !== '') $b->where('rc.workflow_status', $status);
        if ($priority !== '') $b->where('tc.priority', $priority);
        $total = $b->countAllResults(false);
        $rows = $b->orderBy('tc.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return $this->response->setJSON(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage]);
    }

    /** ایجاد/ویرایش سناریو در بخش — همیشه فعال و بلافاصله در صف تست کارتابل آزمونگر */
    public function saveCase(int $nodeId)
    {
        $id = (int) ($this->request->getPost('id') ?? 0);
        $title = trim((string) $this->request->getPost('title'));
        $expected = trim((string) $this->request->getPost('expected_result'));
        $priority = strtoupper((string) $this->request->getPost('priority'));
        $testType = strtoupper((string) $this->request->getPost('test_type'));
        $stepsRaw = trim((string) $this->request->getPost('steps'));
        if (mb_strlen($title) < 3) return $this->jsonError(t('cases.title_min'));
        if (mb_strlen($expected) < 3) return $this->jsonError(t('cases.expected_min'));
        if (!isset(WorkflowService::priorityMeta()[$priority])) $priority = 'MEDIUM';
        $validTypes = ['FUNCTIONAL', 'UI', 'SECURITY', 'REGRESSION', 'OTHER'];
        if (!in_array($testType, $validTypes, true)) $testType = 'FUNCTIONAL';

        $db = db_connect();
        // پس از شروع تست، سناریو فقط قابل مشاهده است
        if ($id) {
            $started = $db->query(
                'SELECT rc.workflow_status FROM test_run_cases rc WHERE rc.test_case_id = ? ORDER BY rc.id DESC LIMIT 1', [$id]
            )->getFirstRow('array');
            if ($started && !in_array($started['workflow_status'], ['READY', 'CANCELLED'], true))
                return $this->jsonError(t('cases.locked_err'));
        }
        $node = $db->table('project_nodes n')->select('n.id, n.project_id')
            ->where('n.id', $nodeId)->where('n.is_active', 1)->get()->getFirstRow('array');
        if (!$node) return $this->jsonError(t('tree.node_not_found'));
        $now = gmdate('Y-m-d H:i:s');
        $fields = [
            'title' => $title,
            'objective' => trim((string) $this->request->getPost('objective')) ?: null,
            'preconditions' => trim((string) $this->request->getPost('preconditions')) ?: null,
            'test_data' => trim((string) $this->request->getPost('test_data')) ?: null,
            'expected_result' => $expected,
            'priority' => $priority, 'test_type' => $testType, 'status' => 'ACTIVE',
            'updated_by' => session('user_id'), 'updated_at' => $now,
        ];
        $wf = new WorkflowService();
        $db->transBegin();
        try {
            if ($id) {
                $owned = $db->table('test_cases')->where('id', $id)->where('node_id', $nodeId)->countAllResults();
                if (!$owned) { $db->transRollback(); return $this->jsonError(t('cases.not_found')); }
                $db->table('test_cases')->update($fields, ['id' => $id]);
                $this->replaceSteps($id, $stepsRaw);
                $wf->snapshotRevision($id, (int) session('user_id'), 'ویرایش سناریو');
                $caseId = $id;
                $msg = t('cases.edited_msg');
            } else {
                $code = strtoupper(trim((string) $this->request->getPost('code')));
                if ($code === '') $code = $this->nextCaseCode((int) $node['project_id']);
                if ($db->table('test_cases')->where('project_id', $node['project_id'])->where('code', $code)->countAllResults()) {
                    $db->transRollback();
                    return $this->jsonError(t('cases.dup_code', ['code' => $code]));
                }
                $db->table('test_cases')->insert($fields + [
                    'project_id' => $node['project_id'], 'node_id' => $nodeId, 'code' => $code,
                    'current_revision' => 1, 'created_by' => session('user_id'), 'created_at' => $now,
                ]);
                $caseId = (int) $db->insertID();
                $this->replaceSteps($caseId, $stepsRaw);
                $wf->snapshotRevision($caseId, (int) session('user_id'), 'ایجاد سناریو');
                $msg = t('cases.created_msg');
            }
            $wf->addCaseToOpenRun($caseId, (int) session('user_id'));
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->jsonError($e->getMessage());
        }
        return $this->response->setJSON(['ok' => true, 'message' => $msg, 'id' => $caseId]);
    }

    /** بایگانی نرم سناریو — فقط قبل از شروع تست */
    public function archiveCase(int $id)
    {
        $db = db_connect();
        $tc = $db->table('test_cases')->where('id', $id)->where('archived_at', null)->countAllResults();
        if (!$tc) return $this->jsonError(t('cases.not_found'));
        $started = $db->query(
            'SELECT rc.workflow_status FROM test_run_cases rc WHERE rc.test_case_id = ? ORDER BY rc.id DESC LIMIT 1', [$id]
        )->getFirstRow('array');
        if ($started && !in_array($started['workflow_status'], ['READY', 'CANCELLED'], true))
            return $this->jsonError(t('cases.locked_archive_err'));
        $db->table('test_cases')->update(['status' => 'ARCHIVED', 'archived_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]);
        return $this->response->setJSON(['ok' => true, 'message' => t('cases.archived_msg')]);
    }

    private function replaceSteps(int $caseId, string $stepsRaw): void
    {
        $db = db_connect();
        $db->table('test_case_steps')->where('test_case_id', $caseId)->delete();
        $order = 1;
        foreach (preg_split('/\r\n|\r|\n/', $stepsRaw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            [$instruction, $expected] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);
            $db->table('test_case_steps')->insert([
                'test_case_id' => $caseId, 'step_order' => $order++,
                'instruction' => $instruction, 'expected_result' => $expected ?: null,
            ]);
        }
    }

    private function nextCaseCode(int $projectId): string
    {
        $db = db_connect();
        $n = (int) $db->query('SELECT COUNT(*) n FROM test_cases WHERE project_id = ?', [$projectId])->getRowArray()['n'] + 1;
        $prefix = $db->table('projects')->select('code')->where('id', $projectId)->get()->getFirstRow('array')['code'] ?? 'TC';
        do {
            $code = $prefix . '-TC-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $n++;
        } while ($db->table('test_cases')->where('project_id', $projectId)->where('code', $code)->countAllResults());
        return $code;
    }

    /* ------------------------- Import اکسل / CSV ------------------------- */

    /** مرحله ۱: آپلود، تجزیه و اعتبارسنجی — پیش‌نمایش در سشن ذخیره می‌شود */
    public function importPreview(int $nodeId)
    {
        $file = $this->request->getFile('file');
        if (!$file || !$file->isValid()) return $this->jsonError(t('imp.no_file'));
        $ext = strtolower($file->getClientExtension());
        if (!in_array($ext, ['xlsx', 'csv'], true)) return $this->jsonError(t('imp.bad_ext'));
        if ($file->getSize() > 2 * 1024 * 1024) return $this->jsonError(t('imp.too_big'));
        $rows = $ext === 'xlsx' ? $this->parseXlsx($file->getTempName()) : $this->parseCsv($file->getTempName());
        if ($rows === null) return $this->jsonError(t('imp.unreadable'));
        if (count($rows) > 200) return $this->jsonError(t('imp.too_many'));

        $db = db_connect();
        $node = $db->table('project_nodes n')->select('n.id, n.project_id')
            ->join('node_assignments na', 'na.node_id = n.id', 'left')->where('n.id', $nodeId)->get()->getFirstRow('array');
        if (!$node) return $this->jsonError(t('tree.node_not_found'));

        $valid = [];
        $errors = [];
        foreach ($rows as $i => $r) {
            $line = $i + 2; // +۱ سرستون، +۱ شمارش انسانی
            $title = trim((string) ($r['عنوان'] ?? $r['title'] ?? ''));
            $expected = trim((string) ($r['نتیجه مورد انتظار'] ?? $r['expected_result'] ?? ''));
            if ($title === '' && $expected === '') continue; // ردیف خالی
            if (mb_strlen($title) < 3) { $errors[] = t('imp.row', ['n' => $line]) . t('imp.err.title'); continue; }
            if (mb_strlen($expected) < 3) { $errors[] = t('imp.row', ['n' => $line]) . t('imp.err.expected'); continue; }
            $priority = strtoupper(trim((string) ($r['اولویت'] ?? $r['priority'] ?? 'MEDIUM')));
            if (!isset(WorkflowService::priorityMeta()[$priority])) {
                $errors[] = t('imp.row', ['n' => $line]) . t('imp.err.priority');
                continue;
            }
            $code = strtoupper(trim((string) ($r['کد'] ?? $r['code'] ?? '')));
            if ($code !== '' && $db->table('test_cases')->where('project_id', $node['project_id'])->where('code', $code)->countAllResults()) {
                $errors[] = t('imp.row', ['n' => $line]) . t('imp.err.dup_code', ['code' => $code]);
                continue;
            }
            $valid[] = [
                'code' => $code, 'title' => $title,
                'objective' => trim((string) ($r['هدف'] ?? $r['objective'] ?? '')),
                'preconditions' => trim((string) ($r['پیش‌نیازها'] ?? $r['پیش‌نیاز'] ?? $r['preconditions'] ?? '')),
                'steps' => trim((string) ($r['مراحل'] ?? $r['steps'] ?? '')),
                'test_data' => trim((string) ($r['داده تست'] ?? $r['test_data'] ?? '')),
                'expected_result' => $expected, 'priority' => $priority,
            ];
        }
        if (count($valid) === 0 && count($errors) > 0)
            return $this->response->setJSON(['ok' => false, 'message' => t('imp.no_valid'), 'errors' => $errors]);
        session()->set('import_preview_' . $nodeId, ['rows' => $valid, 'node' => $nodeId]);
        return $this->response->setJSON([
            'ok' => true, 'message' => t('imp.valid_rows', ['n' => count($valid)]),
            'errors' => $errors, 'preview' => array_slice($valid, 0, 5),
        ]);
    }

    /** مرحله ۲: درج تراکنشی همه-یا-هیچ */
    public function importCommit(int $nodeId)
    {
        $key = 'import_preview_' . $nodeId;
        $data = session()->get($key);
        if (!$data || !is_array($data)) return $this->jsonError(t('imp.no_preview'));
        session()->remove($key);
        $db = db_connect();
        $node = $db->table('project_nodes n')->select('n.project_id')
            ->join('node_assignments na', 'na.node_id = n.id', 'left')->where('n.id', $nodeId)->get()->getFirstRow('array');
        if (!$node) return $this->jsonError(t('tree.node_not_found'));
        // هر سناریو بلافاصله فعال و در صف تست کارتابل آزمونگر است
        $wf = new WorkflowService();
        $now = gmdate('Y-m-d H:i:s');
        $db->transBegin();
        $count = 0;
        try {
            foreach ($data['rows'] as $r) {
                $code = $r['code'] !== '' ? $r['code'] : $this->nextCaseCode((int) $node['project_id']);
                $db->table('test_cases')->insert([
                    'project_id' => $node['project_id'], 'node_id' => $nodeId, 'code' => $code, 'title' => $r['title'],
                    'objective' => $r['objective'] ?: null, 'preconditions' => $r['preconditions'] ?: null,
                    'test_data' => $r['test_data'] ?: null, 'expected_result' => $r['expected_result'],
                    'priority' => $r['priority'], 'test_type' => 'FUNCTIONAL', 'status' => 'ACTIVE',
                    'current_revision' => 1, 'created_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now,
                ]);
                $caseId = (int) $db->insertID();
                $this->replaceSteps($caseId, $r['steps']);
                $wf->snapshotRevision($caseId, (int) session('user_id'), 'ورود گروهی از اکسل');
                $wf->addCaseToOpenRun($caseId, (int) session('user_id'));
                $count++;
            }
            $db->table('import_jobs')->insert([
                'user_id' => session('user_id'), 'project_id' => $node['project_id'],
                'file_name' => 'import-node-' . $nodeId, 'status' => 'DONE', 'success_count' => $count,
                'error_count' => 0, 'created_at' => $now,
            ]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->jsonError('درج ناموفق بود: ' . $e->getMessage());
        }
        return $this->response->setJSON(['ok' => true, 'message' => t('imp.committed', ['n' => $count])]);
    }

    private function parseXlsx(string $tmp): ?array
    {
        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        } catch (\Throwable $e) {
            return null;
        }
        $rows = $sheet->toArray(null, true, true, false);
        if (count($rows) < 2) return null;
        $headers = array_map(fn($h) => trim((string) $h), array_shift($rows));
        $out = [];
        foreach ($rows as $r) {
            $assoc = [];
            foreach ($headers as $i => $h) $assoc[$h] = $r[$i] ?? '';
            $out[] = $assoc;
        }
        return $out;
    }

    private function parseCsv(string $tmp): ?array
    {
        $text = file_get_contents($tmp);
        if ($text === false || $text === '') return null;
        $bom = pack('H*', 'EFBBBF');
        $text = str_starts_with($text, $bom) ? substr($text, 3) : $text;
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        if (count($lines) < 2) return null;
        $delim = str_contains($lines[0], "\t") ? "\t" : (str_contains($lines[0], ';') ? ';' : ',');
        $headers = array_map(fn($h) => trim($h, "\" \t"), explode($delim, array_shift($lines)));
        $out = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $vals = array_map(fn($v) => trim($v, "\" \t"), explode($delim, $line));
            $assoc = [];
            foreach ($headers as $i => $h) $assoc[$h] = $vals[$i] ?? '';
            $out[] = $assoc;
        }
        return $out;
    }

    /** دانلود قالب XLSX */
    public function template()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TestCases');
        $headers = ['کد', 'عنوان', 'هدف', 'پیش‌نیازها', 'مراحل', 'داده تست', 'نتیجه مورد انتظار', 'اولویت'];
        $sample = ['', 'ورود کاربر با نام و رمز معتبر', 'بررسی صحت فرایند ورود', 'کاربر در صفحهٔ ورود باشد', "نمایش صفحه ورود|دیده شدن فرم\nوارد کردن نام کاربری|دیده شدن مقدار\nکلیک دکمه ورود|انتقال به داشبورد", 'user: admin / pass: 123456', 'کاربر به داشبورد هدایت شود', 'HIGH'];
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $h);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        foreach ($sample as $i => $v) $sheet->getCell([$i + 1, 2])->setValue($v);
        $guide = $spreadsheet->createSheet();
        $guide->setTitle('راهنما');
        $guideLines = [
            'راهنمای ورود گروهی سناریوها',
            'ستون «کد» اختیاری است؛ خالی بگذارید تا خودکار تولید شود.',
            '«عنوان» و «نتیجهٔ مورد انتظار» الزامی هستند.',
            '«مراحل»: هر مرحله در یک خط؛ اگر مرحله نتیجهٔ مورد انتظار دارد با | جدا کنید (متن|نتیجه).',
            '«اولویت»: LOW یا MEDIUM یا HIGH یا CRITICAL.',
            'حداکثر ۲۰۰ ردیف و ۲ مگابایت در هر نوبت.',
        ];
        foreach ($guideLines as $i => $line) $guide->setCellValue('A' . ($i + 1), $line);
        $guide->getColumnDimension('A')->setAutoSize(true);
        $file = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($file);
        return $this->response->download('test_cases_template.xlsx', file_get_contents($file))->setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function jsonError(string $msg)
    {
        return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $msg]);
    }
}
