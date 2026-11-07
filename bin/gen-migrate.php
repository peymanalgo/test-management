<?php
/** تولید فایل SQL برای انتقال داده‌ها به هاست */
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=ai_test_manager', 'root', '');
$pdo->exec('SET NAMES utf8mb4');

$out = "-- =====================================================================\n";
$out .= "-- آزمون‌یار — انتقال داده‌ها به هاست\n";
$out .= "-- این فایل را در phpMyAdmin هاست Import کنید.\n";
$out .= "-- =====================================================================\n";
$out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

$out .= "INSERT IGNORE INTO roles (id, code, name, created_at) VALUES\n";
$out .= "  (1,'TESTER','آزمونگر',UTC_TIMESTAMP()),\n  (2,'DEVELOPER','برنامه‌نویس',UTC_TIMESTAMP()),\n  (3,'MANAGER','مدیر پروژه',UTC_TIMESTAMP());\n\n";

$users = $pdo->query("SELECT id, username, display_name, email, password_hash, is_active, is_admin, must_change_password FROM users WHERE username != 'admin' AND deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
$out .= "-- کاربران (" . count($users) . ")\n";
$out .= "INSERT INTO users (id, username, display_name, email, password_hash, is_active, is_admin, must_change_password, created_at) VALUES\n";
$vals = [];
foreach ($users as $u) {
    $email = $u['email'] ? "'{$u['email']}'" : 'NULL';
    $vals[] = "({$u['id']},'{$u['username']}','{$u['display_name']}',$email,'{$u['password_hash']}',{$u['is_active']},{$u['is_admin']},{$u['must_change_password']},UTC_TIMESTAMP())";
}
$out .= implode(",\n", $vals) . ";\n\n";

$ur = $pdo->query('SELECT user_id, role_id FROM user_roles')->fetchAll(PDO::FETCH_ASSOC);
$out .= "INSERT IGNORE INTO user_roles (user_id, role_id) VALUES\n";
$vals = [];
foreach ($ur as $r) $vals[] = "({$r['user_id']},{$r['role_id']})";
$out .= implode(',', $vals) . ";\n\n";

$proj = $pdo->query('SELECT id, code, name, description FROM projects WHERE code = "AZ"')->fetch(PDO::FETCH_ASSOC);
if ($proj) {
    $desc = $proj['description'] ? "'" . addslashes($proj['description']) . "'" : 'NULL';
    $out .= "INSERT INTO projects (id, code, name, description, status, created_by, created_at, updated_at) VALUES\n";
    $out .= "  ({$proj['id']},'{$proj['code']}','{$proj['name']}',$desc,'ACTIVE',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())\n";
    $out .= "ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description);\n\n";
}

$run = $pdo->query("SELECT id, project_id, name FROM test_runs WHERE project_id = {$proj['id']} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($run) {
    $out .= "INSERT INTO test_runs (id, project_id, name, description, status, created_by, created_at, updated_at) VALUES\n";
    $out .= "  ({$run['id']},{$run['project_id']},'{$run['name']}',NULL,'OPEN',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())\n";
    $out .= "ON DUPLICATE KEY UPDATE name=VALUES(name);\n\n";
}

$nodes = $pdo->query("SELECT id, project_id, parent_id, name, sort_order FROM project_nodes WHERE project_id = {$proj['id']} AND is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$out .= "-- درخت (" . count($nodes) . " بخش)\n";
$out .= "INSERT INTO project_nodes (id, project_id, parent_id, name, sort_order, is_active, created_at, updated_at) VALUES\n";
$vals = [];
foreach ($nodes as $n) {
    $pid = $n['parent_id'] ?: 'NULL';
    $vals[] = "({$n['id']},{$n['project_id']},$pid,'" . addslashes($n['name']) . "',{$n['sort_order']},1,UTC_TIMESTAMP(),UTC_TIMESTAMP())";
}
$out .= implode(",\n", $vals) . ";\n\n";

$na = $pdo->query("SELECT na.node_id, na.tester_id, na.developer_id, na.manager_id FROM node_assignments na JOIN project_nodes n ON n.id = na.node_id WHERE n.project_id = {$proj['id']}")->fetchAll(PDO::FETCH_ASSOC);
$out .= "INSERT INTO node_assignments (node_id, tester_id, developer_id, manager_id, assigned_by, created_at, updated_at) VALUES\n";
$vals = [];
foreach ($na as $a) $vals[] = "({$a['node_id']},{$a['tester_id']},{$a['developer_id']},{$a['manager_id']},1,UTC_TIMESTAMP(),UTC_TIMESTAMP())";
$out .= implode(",\n", $vals) . ";\n\n";

$cases = $pdo->query("SELECT tc.id, tc.project_id, tc.node_id, tc.code, tc.title, tc.objective, tc.preconditions, tc.test_data, tc.expected_result, tc.priority, tc.test_type, tc.status, tc.current_revision FROM test_cases tc WHERE tc.project_id = {$proj['id']} AND tc.archived_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
$out .= "-- سناریوها (" . count($cases) . ")\n";
$out .= "INSERT INTO test_cases (id, project_id, node_id, code, title, objective, preconditions, test_data, expected_result, priority, test_type, status, current_revision, created_by, created_at, updated_at) VALUES\n";
$vals = [];
foreach ($cases as $c) {
    $obj = $c['objective'] ? "'" . addslashes($c['objective']) . "'" : 'NULL';
    $pre = $c['preconditions'] ? "'" . addslashes($c['preconditions']) . "'" : 'NULL';
    $td = $c['test_data'] ? "'" . addslashes($c['test_data']) . "'" : 'NULL';
    $vals[] = "({$c['id']},{$c['project_id']},{$c['node_id']},'{$c['code']}','" . addslashes($c['title']) . "',$obj,$pre,$td,'" . addslashes($c['expected_result']) . "','{$c['priority']}','{$c['test_type']}','{$c['status']}',{$c['current_revision']},1,UTC_TIMESTAMP(),UTC_TIMESTAMP())";
}
$out .= implode(",\n", $vals) . ";\n\n";

$steps = $pdo->query("SELECT s.test_case_id, s.step_order, s.instruction, s.expected_result FROM test_case_steps s JOIN test_cases tc ON tc.id = s.test_case_id WHERE tc.project_id = {$proj['id']} ORDER BY s.test_case_id, s.step_order")->fetchAll(PDO::FETCH_ASSOC);
$out .= "-- مراحل (" . count($steps) . ")\n";
$out .= "INSERT INTO test_case_steps (test_case_id, step_order, instruction, expected_result) VALUES\n";
$vals = [];
foreach ($steps as $s) {
    $exp = $s['expected_result'] ? "'" . addslashes($s['expected_result']) . "'" : 'NULL';
    $vals[] = "({$s['test_case_id']},{$s['step_order']},'" . addslashes($s['instruction']) . "',$exp)";
}
$out .= implode(",\n", $vals) . ";\n\n";

$revs = $pdo->query("SELECT r.test_case_id, r.revision_no, r.snapshot_json, r.reason FROM test_case_revisions r JOIN test_cases tc ON tc.id = r.test_case_id WHERE tc.project_id = {$proj['id']}")->fetchAll(PDO::FETCH_ASSOC);
$out .= "-- Revisions (" . count($revs) . ")\n";
$out .= "INSERT INTO test_case_revisions (test_case_id, revision_no, snapshot_json, reason, created_by, created_at) VALUES\n";
$vals = [];
foreach ($revs as $r) {
    $snap = addslashes($r['snapshot_json']);
    $reason = $r['reason'] ? "'" . addslashes($r['reason']) . "'" : 'NULL';
    $vals[] = "({$r['test_case_id']},{$r['revision_no']},'$snap',$reason,1,UTC_TIMESTAMP())";
}
$out .= implode(",\n", $vals) . ";\n\n";

$rcs = $pdo->query("SELECT rc.id, rc.test_run_id, rc.test_case_id, rc.test_case_revision_id, rc.workflow_status FROM test_run_cases rc JOIN test_runs tr ON tr.id = rc.test_run_id WHERE tr.project_id = {$proj['id']}")->fetchAll(PDO::FETCH_ASSOC);
$out .= "-- آیتم‌های دوره (" . count($rcs) . ")\n";
$out .= "INSERT INTO test_run_cases (id, test_run_id, test_case_id, test_case_revision_id, workflow_status, lock_version, added_at) VALUES\n";
$vals = [];
foreach ($rcs as $rc) $vals[] = "({$rc['id']},{$rc['test_run_id']},{$rc['test_case_id']},{$rc['test_case_revision_id']},'{$rc['workflow_status']}',1,UTC_TIMESTAMP())";
$out .= implode(",\n", $vals) . ";\n\n";

$whs = $pdo->query("SELECT wh.test_run_case_id, wh.from_status, wh.to_status, wh.actor_user_id, wh.reason, wh.created_at FROM workflow_history wh JOIN test_run_cases rc ON rc.id = wh.test_run_case_id JOIN test_runs tr ON tr.id = rc.test_run_id WHERE tr.project_id = {$proj['id']} ORDER BY wh.id")->fetchAll(PDO::FETCH_ASSOC);
$out .= "-- تاریخچه (" . count($whs) . ")\n";
if (!empty($whs)) {
    $out .= "INSERT INTO workflow_history (test_run_case_id, from_status, to_status, actor_user_id, reason, created_at) VALUES\n";
    $vals = [];
    foreach ($whs as $w) {
        $fs = $w['from_status'] ? "'" . $w['from_status'] . "'" : 'NULL';
        $rs = $w['reason'] ? "'" . addslashes($w['reason']) . "'" : 'NULL';
        $vals[] = "({$w['test_run_case_id']},$fs,'{$w['to_status']}',{$w['actor_user_id']},$rs,'{$w['created_at']}')";
    }
    $out .= implode(",\n", $vals) . ";\n\n";
}

$out .= "SET FOREIGN_KEY_CHECKS = 1;\n-- پایان\n";
file_put_contents('migrate-to-host.sql', $out);
echo 'migrate-to-host.sql: ' . number_format(strlen($out)) . " bytes | " . count($users) . " users | " . count($nodes) . " nodes | " . count($cases) . " cases | " . count($steps) . " steps\n";
