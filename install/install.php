<?php
/**
 * نصب‌کنندهٔ یک‌بارمصرف آزمون‌یار برای هاست cPanel — بدون SSH و بدون Composer.
 *
 * استفاده: پس از بالا بردن فایل‌های پروژه روی هاست، این فایل را از مرورگر باز کنید:
 *   https://your-domain/install.php
 * مراحل: بررسی پیش‌نیازها ← اطلاعات دیتابیس ← ساخت جدول‌ها ← ایجاد مدیر ← تولید .env ← قفل خودکار.
 * پس از نصب موفق، این فایل را حذف کنید (خودش هم قفل می‌شود).
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
mb_internal_encoding('UTF-8');

$ROOT = dirname(__FILE__);
$LOCK = $ROOT . '/install.lock';
$SELF_LOCKED = false;
if (is_file($LOCK)) $SELF_LOCKED = true;

step_router();

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function step_router(): void
{
    global $SELF_LOCKED;
    $step = $_POST['step'] ?? '1';
    if ($SELF_LOCKED) { render_page('نصب قفل شده است', locked_body()); return; }
    if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') do_install();
    render_page('نصب آزمون‌یار', step1_body());
}

function checks(): array
{
    $ext = fn(string $e) => ['ok' => extension_loaded($e), 'label' => "افزونهٔ $e"];
    $writable = static function (string $p) { return ['ok' => is_dir($p) ? is_writable($p) : is_writable(dirname($p)), 'label' => "قابل نوشتن: $p"]; };
    return array_merge([
        ['ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'label' => 'PHP ≥ 8.2 (فعلی: ' . PHP_VERSION . ')'],
        $ext('mysqli'), $ext('mbstring'), $ext('intl'), $ext('json'), $ext('fileinfo'), $ext('gd'), $ext('zip'), $ext('dom'), $ext('xml'),
        $writable(__DIR__), $writable(__DIR__ . '/writable'),
        ['ok' => is_file(__DIR__ . '/install/schema.sql') && is_readable(__DIR__ . '/install/schema.sql'), 'label' => 'install/schema.sql موجود و قابل خواندن'],
    ]);
}

function step1_body(): string
{
    $rows = '';
    $allOk = true;
    foreach (checks() as $c) {
        $allOk = $allOk && $c['ok'];
        $rows .= '<tr><td>' . h($c['label']) . '</td><td>' . ($c['ok'] ? '<b style="color:#16a34a">✓</b>' : '<b style="color:#dc2626">✕</b>') . '</td></tr>';
    }
    $base = 'http' . (($_SERVER['HTTPS'] ?? '') === 'on' ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return <<<HTML
<table class="tbl"><thead><tr><th>بررسی</th><th>نتیجه</th></tr></thead><tbody>$rows</tbody></table>
<h3 style="margin:22px 0 10px">اطلاعات دیتابیس MySQL (از cPanel بسازید)</h3>
<form method="post" class="frm">
  <input type="hidden" name="step" value="2">
  <label>میزبان DB<input name="db_host" value="localhost" required></label>
  <label>نام دیتابیس<input name="db_name" placeholder="cpaneluser_azmoon" required></label>
  <label>نام کاربری DB<input name="db_user" placeholder="cpaneluser_dbuser" required></label>
  <label>رمز عبور DB<input type="password" name="db_pass" required></label>
  <label>رمز مدیر سیستم (حداقل ۱۲ نویسه)<input name="admin_pass" minlength="12" required></label>
  <label>نشانی پایهٔ سامانه<input name="base_url" value="{$base}/" required></label>
  <button type="submit" ' . ($allOk ? '' : 'disabled title="ابتدا موارد قرمز را رفع کنید"') . '>شروع نصب</button>
</form>
HTML;
}

function do_install(): void
{
    global $ROOT;
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/') . '/';
    try {
        if ($dbName === '' || $dbUser === '' || strlen($adminPass) < 12) throw new RuntimeException('همهٔ فیلدها الزامی است و رمز مدیر حداقل ۱۲ نویسه باشد.');
        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('SET NAMES utf8mb4, FOREIGN_KEY_CHECKS = 0');
        $schemaFile = $ROOT . '/install/schema.sql';
        $sql = @file_get_contents($schemaFile);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('فایل install/schema.sql خوانده نشد (' . $schemaFile . '). مطمئن شوید پوشهٔ install با فایل schema.sql درست کنار install.php (داخل همان پوشهٔ public_html) قرار دارد و قابل خواندن است (سطح دسترسی ۶۴۴).');
        }
        foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $stmt) {
            if ($stmt !== '' && !str_starts_with($stmt, '--')) $pdo->exec($stmt);
        }
        $pdo->prepare('INSERT INTO users (username, display_name, password_hash, is_admin, is_active, must_change_password, created_at) VALUES ("admin", "مدیر سیستم", ?, 1, 1, 0, UTC_TIMESTAMP())')
            ->execute([password_hash($adminPass, PASSWORD_ARGON2ID)]);
        // .env
        $env = <<<ENV
CI_ENVIRONMENT = production

app.baseURL = '{$baseUrl}'
app.indexPage = ''

database.default.hostname = {$dbHost}
database.default.database = {$dbName}
database.default.username = {$dbUser}
database.default.password = '{$dbPass}'
database.default.DBDriver = MySQLi
database.default.port = 3306

INITIAL_ADMIN_PASSWORD = '{$adminPass}'
ENV;
        if (@file_put_contents($ROOT . '/.env', $env) === false) throw new RuntimeException('نوشتن .env ممکن نشد — دسترسی پوشه را بررسی کنید.');
        // مسیر index.php اگر public به public_html منتقل شده باشد درست شود
        fix_public_index($ROOT);
        @mkdir($ROOT . '/writable/cache', 0755, true);
        @mkdir($ROOT . '/writable/logs', 0755, true);
        @mkdir($ROOT . '/writable/session', 0755, true);
        @mkdir($ROOT . '/writable/uploads', 0755, true);
        file_put_contents($ROOT . '/install.lock', date('c'));
        render_page('نصب کامل شد ✓', '<div class="ok"><b>آزمون‌یار با موفقیت نصب شد!</b><br>ورود: <code>' . h($baseUrl) . '</code> با نام کاربری <code>admin</code>.<br><br>⚠ برای امنیت، فایل <code>install.php</code> را از هاست حذف کنید (این نصب‌کننده هم قفل شد).</div>', true);
    } catch (Throwable $e) {
        render_page('خطا در نصب', '<div class="err">' . h($e->getMessage()) . '<br><br><a href="install.php">تلاش مجدد</a></div>');
    }
}

/** مسیر bootstrap در index.php عمومی را به پروژهٔ اصلی (مثلاً ~/azmoon-yar) وصل می‌کند */
function fix_public_index(string $root): void
{
    $idx = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/index.php';
    if ($idx === '/index.php' || !is_file($idx)) return;
    $content = @file_get_contents($idx);
    if (!is_string($content) || !str_contains($content, 'Paths.php')) return;
    // اگر مسیر فعلیِ index به فایل موجودی اشاره می‌کند، دست نمی‌زنیم
    if (preg_match("#require FCPATH \.\s*'([^']*Paths\.php)'#", $content, $m)) {
        $p = $m[1];
        if (str_starts_with($p, '../')) $p = dirname($idx) . '/' . $p;
        elseif (!str_starts_with($p, '/')) $p = dirname($idx) . '/' . $p;
        if (is_file($p)) return;
    }
    $cands = [
        dirname($idx) . '/azmoon-yar/app/Config/Paths.php', // public_html کنارِ ~/azmoon-yar
        dirname($root) . '/azmoon-yar/app/Config/Paths.php',
        $root . '/app/Config/Paths.php', // کل پروژه داخل public_html
    ];
    foreach ($cands as $c) {
        if (is_file($c)) {
            $fixed = preg_replace("#require FCPATH \.\s*'[^']*Paths\.php';#", "require FCPATH . '" . str_replace('\\', '/', $c) . "';", $content, 1);
            if ($fixed) @file_put_contents($idx, $fixed);
            return;
        }
    }
}

function locked_body(): string
{
    return '<div class="ok">نصب‌کننده قبلاً اجرا و قفل شده است. اگر نیاز به نصب مجدد دارید فایل <code>install.lock</code> را حذف کنید.</div>';
}

function render_page(string $title, string $body, bool $done = false): void
{
    ?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<style>
body{font:14px/1.9 Tahoma,Arial,sans-serif;background:#141f47;color:#1c2947;margin:0;padding:30px 14px}
.wrap{max-width:760px;margin:0 auto;background:#fff;border-radius:18px;padding:26px 28px;box-shadow:0 24px 60px rgba(0,0,0,.35)}
h1{font-size:20px;margin:0 0 4px;color:#2a3f7e}p.sub{color:#76839b;margin:0 0 18px;font-size:12.5px}
table{width:100%;border-collapse:collapse;font-size:12.5px}th,td{padding:8px 10px;border-bottom:1px solid #e7ebf4;text-align:right}th{background:#f4f6fb}
.frm label{display:block;font-size:12px;font-weight:700;margin:12px 0 4px}
.frm input{width:100%;box-sizing:border-box;padding:9px 12px;border:1.5px solid #ccd5e8;border-radius:10px;font-size:13px}
.frm input:focus{outline:0;border-color:#6957e8;box-shadow:0 0 0 3px #efeaff}
.frm button{margin-top:18px;width:100%;padding:12px;border:0;border-radius:11px;background:linear-gradient(120deg,#6957e8,#19a4b8);color:#fff;font:700 14px Tahoma;cursor:pointer}
.frm button:disabled{opacity:.5;cursor:not-allowed}
.ok{background:#e3f7ea;border:1px solid #b6e5c5;color:#14532d;border-radius:12px;padding:14px 16px;font-size:13px}
.err{background:#fde5e5;border:1px solid #f5c2c2;color:#7f1d1d;border-radius:12px;padding:14px 16px;font-size:13px}
code{background:#f1f4fa;border-radius:6px;padding:1px 7px;font-size:12px}
</style></head><body><div class="wrap">
<h1>✓ نصب‌کنندهٔ آزمون‌یار</h1><p class="sub">نصب یک‌بارمصرف روی هاست cPanel — بدون SSH و Composer</p>
<?= $body ?>
</div></body></html><?php
    if ($done) exit;
}
