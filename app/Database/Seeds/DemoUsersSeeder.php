<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * کاربران سه نقش (برای تست ورود) + دادهٔ نمایشی یک پروژه با درخت، مسئولان و سناریوها.
 *   tester / developer / manager — رمز هر سه: Pe123Ym321An (درخواست صریح مالک پروژه برای حساب‌های تستی)
 * idempotent است؛ در صورت وجود دادهٔ قبلی تغییری نمی‌دهد.
 */
class DemoUsersSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Pe123Ym321An';

    public function run()
    {
        $db = db_connect();
        $now = gmdate('Y-m-d H:i:s');

        $roles = [];
        foreach ($db->table('roles')->get()->getResultArray() as $r) $roles[$r['code']] = $r['id'];
        if (!$roles) {
            $db->table('roles')->insertBatch([
                ['code' => 'TESTER', 'name' => 'آزمونگر', 'created_at' => $now],
                ['code' => 'DEVELOPER', 'name' => 'برنامه‌نویس', 'created_at' => $now],
                ['code' => 'MANAGER', 'name' => 'مدیر پروژه', 'created_at' => $now],
            ]);
            foreach ($db->table('roles')->get()->getResultArray() as $r) $roles[$r['code']] = $r['id'];
        }

        $ensureUser = function (string $username, string $name, string $password, string $roleCode) use ($db, $roles, $now): int {
            $existing = $db->table('users')->where('username', $username)->get()->getFirstRow('array');
            if ($existing) return (int) $existing['id'];
            $db->table('users')->insert([
                'username' => $username, 'display_name' => $name,
                'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
                'is_active' => 1, 'is_admin' => 0, 'must_change_password' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $uid = (int) $db->insertID();
            $db->table('user_roles')->insert(['user_id' => $uid, 'role_id' => $roles[$roleCode]]);
            return $uid;
        };

        $testerId = $ensureUser('tester', 'رضا آزمونگر', self::DEMO_PASSWORD, 'TESTER');
        $developerId = $ensureUser('developer', 'سعید برنامه‌نویس', self::DEMO_PASSWORD, 'DEVELOPER');
        $managerId = $ensureUser('manager', 'مینا مدیرپروژه', self::DEMO_PASSWORD, 'MANAGER');
        $adminId = (int) ($db->table('users')->where('username', 'admin')->get()->getFirstRow('array')['id'] ?? $managerId);

        if ($db->table('projects')->where('code', 'SHOP')->countAllResults()) return; // قبلاً اجرا شده

        // پروژهٔ نمایشی
        $db->table('projects')->insert([
            'code' => 'SHOP', 'name' => 'فروشگاه اینترنتی آریا', 'status' => 'ACTIVE', 'created_by' => $adminId,
            'description' => 'سامانهٔ فروش آنلاین تولیدشده با هوش مصنوعی — دادهٔ نمایشی', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $projectId = (int) $db->insertID();

        $node = function (string $name, ?int $parentId) use ($db, $projectId, $now): int {
            $db->table('project_nodes')->insert([
                'project_id' => $projectId, 'parent_id' => $parentId, 'name' => $name, 'sort_order' => 0,
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            return (int) $db->insertID();
        };
        $nAuth = $node('احراز هویت و کاربران', null);
        $nLogin = $node('ورود و خروج', $nAuth);
        $nRegister = $node('ثبت‌نام', $nAuth);
        $nShop = $node('فروش و سبد خرید', null);
        $nCart = $node('سبد خرید', $nShop);
        $nCheckout = $node('پرداخت', $nShop);

        $assign = function (int $nodeId) use ($db, $testerId, $developerId, $managerId, $adminId, $now): void {
            $db->table('node_assignments')->insert([
                'node_id' => $nodeId, 'tester_id' => $testerId, 'developer_id' => $developerId, 'manager_id' => $managerId,
                'assigned_by' => $adminId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        };
        foreach ([$nLogin, $nRegister, $nCart, $nCheckout] as $nid) $assign($nid);

        // دورهٔ باز
        $db->table('test_runs')->insert([
            'project_id' => $projectId, 'name' => 'دورهٔ جاری', 'status' => 'OPEN', 'created_by' => $adminId,
            'description' => 'دورهٔ نمایشی', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $runId = (int) $db->insertID();

        $case = function (int $nodeId, string $code, string $title, string $objective, array $steps, string $expected, string $priority) use ($db, $projectId, $adminId, $now, $runId): int {
            $db->table('test_cases')->insert([
                'project_id' => $projectId, 'node_id' => $nodeId, 'code' => $code, 'title' => $title,
                'objective' => $objective, 'preconditions' => 'کاربر به صفحهٔ مربوط دسترسی دارد.',
                'test_data' => 'کاربر نمونه: demo@aria.shop', 'expected_result' => $expected,
                'priority' => $priority, 'test_type' => 'FUNCTIONAL', 'status' => 'ACTIVE',
                'current_revision' => 1, 'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $cid = (int) $db->insertID();
            foreach ($steps as $i => $s) {
                [$ins, $exp] = array_pad($s, 2, null);
                $db->table('test_case_steps')->insert([
                    'test_case_id' => $cid, 'step_order' => $i + 1, 'instruction' => $ins, 'expected_result' => $exp,
                ]);
            }
            $db->table('test_case_revisions')->insert([
                'test_case_id' => $cid, 'revision_no' => 1,
                'snapshot_json' => json_encode(['title' => $title, 'expected' => $expected], JSON_UNESCAPED_UNICODE),
                'reason' => 'ایجاد اولیه (دادهٔ نمایشی)', 'created_by' => $adminId, 'created_at' => $now,
            ]);
            $revId = (int) $db->insertID();
            $db->table('test_run_cases')->insert([
                'test_run_id' => $runId, 'test_case_id' => $cid, 'test_case_revision_id' => $revId,
                'workflow_status' => 'READY', 'lock_version' => 1, 'added_at' => $now,
            ]);
            $rcId = (int) $db->insertID();
            $db->table('workflow_history')->insert([
                'test_run_case_id' => $rcId, 'from_status' => null, 'to_status' => 'READY',
                'actor_user_id' => $adminId, 'reason' => 'افزوده شدن به دورهٔ تست', 'created_at' => $now,
            ]);
            return $rcId;
        };

        $rc1 = $case($nLogin, 'SHOP-TC-001', 'ورود با نام کاربری و رمز معتبر', 'بررسی صحت ورود کاربر', [
            ['نمایش صفحهٔ ورود', 'فرم ورود نمایش داده شود'],
            ['وارد کردن نام کاربری و رمز صحیح', 'مقادیر پذیرفته شود'],
            ['کلیک روی دکمهٔ ورود', 'انتقال به داشبورد'],
        ], 'کاربر وارد حساب خود شده و به داشبورد هدایت می‌شود.', 'HIGH');
        $case($nLogin, 'SHOP-TC-002', 'ورود با رمز اشتباه', 'بررسی پیام خطا', [
            ['وارد کردن رمز اشتباه', ''],
            ['کلیک روی دکمهٔ ورود', 'پیام «رمز نادرست است» نمایش یابد'],
        ], 'ورود انجام نشود و پیام خطای مناسب نمایش داده شود.', 'MEDIUM');
        $case($nRegister, 'SHOP-TC-003', 'ثبت‌نام با ایمیل تکراری', 'جلوگیری از ثبت‌نام تکراری', [
            ['وارد کردن ایمیلِ قبلاً ثبت‌شده', 'پیام تکراری بودن نمایش یابد'],
        ], 'ثبت‌نام رد شود و کاربر پیام مناسب ببیند.', 'HIGH');
        $case($nCart, 'SHOP-TC-004', 'افزودن کالا به سبد خرید', 'بررسی عملکرد سبد', [
            ['کلیک روی «افزودن به سبد» روی یک کالا', 'کالا به سبد اضافه شود'],
            ['باز کردن سبد خرید', 'کالا با تعداد و قیمت درست دیده شود'],
        ], 'کالا بدون خطا به سبد اضافه و نمایش داده شود.', 'MEDIUM');
        $case($nCheckout, 'SHOP-TC-005', 'پرداخت موفق با کارت معتبر', 'بررسی فرایند پرداخت', [
            [' تکمیل اطلاعات ارسال', ''],
            ['انتخاب پرداخت آنلاین و وارد کردن کارت معتبر', 'به درگاه منتقل شود'],
            ['تأیید پرداخت در درگاه', 'سفارش با وضعیت پرداخت‌شده ثبت شود'],
        ], 'سفارش با موفقیت ثبت و فاکتور صادر شود.', 'CRITICAL');

        // یکی از سناریوها از قبل توسط آزمونگر PASS شده تا چرخهٔ واقعی دیده شود
        $db->table('test_executions')->insert([
            'test_run_case_id' => $rc1, 'test_case_revision_id' => 1, 'execution_no' => 1, 'tester_id' => $testerId,
            'result' => 'PASS', 'actual_result' => 'ورود انجام شد و داشبورد نمایش داده شد.', 'notes' => 'بدون مشکل',
            'executed_at' => $now, 'created_at' => $now,
        ]);
        $db->table('test_run_cases')->update(['workflow_status' => 'PENDING_APPROVAL'], ['id' => $rc1]);
        $db->table('workflow_history')->insert([
            'test_run_case_id' => $rc1, 'from_status' => 'READY', 'to_status' => 'PENDING_APPROVAL',
            'actor_user_id' => $testerId, 'reason' => 'ثبت نتیجهٔ موفق؛ ارجاع برای تأیید مدیر پروژه', 'created_at' => $now,
        ]);
        $db->table('notifications')->insert([
            'user_id' => $managerId, 'project_id' => $projectId, 'type' => 'APPROVAL', 'title' => 'در انتظار تأیید شما',
            'message' => 'سناریو «SHOP-TC-001 — ورود با نام کاربری و رمز معتبر» با نتیجهٔ PASS در انتظار تأیید شماست.',
            'target_url' => '/workbench', 'created_at' => $now,
        ]);
    }
}
