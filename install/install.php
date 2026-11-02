<?php
/**
 * نصب‌کنندهٔ یک‌بارمصرف آزمون‌یار برای هاست cPanel — بدون SSH و بدون Composer.
 *
 * محل فایل مهم نیست؛ هر دو حالت پشتیبانی می‌شود:
 *   public_html/install.php + public_html/install/schema.sql   (چیدمان استاندارد)
 *   public_html/install/install.php + schema.sql کنارش          (کل پوشهٔ install کپی شده)
 *
 * .env در «ریشهٔ واقعی پروژه» نوشته می‌شود (از روی مسیر app در index.php کشف می‌شود).
 * پس از نصب موفق، کل پوشهٔ install و install.php را حذف کنید.
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
mb_internal_encoding('UTF-8');

$LOCK = __FILE__ . '.lock';

step_router();

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** یافتن schema.sql در چیدمان‌های مختلف */
function schema_path(): ?string
{
    foreach ([__DIR__ . '/install/schema.sql', __DIR__ . '/schema.sql', dirname(__DIR__) . '/install/schema.sql'] as $p) {
        if (is_file($p) && is_readable($p)) return $p;
    }
    return null;
}

/** کشف ریشهٔ واقعی پروژه (پوشه‌ای که app/ و writable/ دارد) */
function project_root(): string
{
    // ۱) از مسیر Paths.php که index.php عمومی require می‌کند
    $idx = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/index.php';
    if (is_file($idx) && preg_match("#require FCPATH \.\s*'([^']*app/Config/Paths\.php)'#", (string) @file_get_contents($idx), $m)) {
        $p = $m[1];
        if (str_starts_with($p, '../')) $p = dirname($idx) . '/' . $p;
        elseif (!str_starts_with($p, '/')) $p = dirname($idx) . '/' . $p;
        $root = dirname(dirname(dirname($p))); // .../app/Config/Paths.php → ریشه
        if (is_dir($root . '/app')) return $root;
    }
    // ۲) نامزدهای رایج
    $cands = [__DIR__, dirname(__DIR__), dirname(__DIR__) . '/azmoon-yar', __DIR__ . '/azmoon-yar', dirname(dirname(__DIR__)) . '/azmoon-yar', $_SERVER['HOME'] ?? '' . '/azmoon-yar'];
    foreach ($cands as $c) if ($c && is_dir(rtrim($c, '/') . '/app')) return rtrim($c, '/');
    // ۳) پیش‌فرض: کنار install.php
    return __DIR__;
}

function step_router(): void
{
    global $LOCK;
    if (is_file($LOCK)) { render_page('نصب قفل شده است', '<div class="ok">نصب‌کننده قبلاً اجرا و قفل شده است. برای نصب مجدد فایل <code>install.php.lock</code> را حذف کنید.</div>'); return; }
    if (($_POST['step'] ?? '1') === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') do_install();
    render_page('نصب آزمون‌یار', step1_body());
}

function checks(): array
{
    $ext = fn(string $e) => ['ok' => extension_loaded($e), 'label' => "افزونهٔ $e"];
    $root = project_root();
    $sp = schema_path();
    return [
        ['ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'label' => 'PHP ≥ 8.2 (فعلی: ' . PHP_VERSION . ')'],
        $ext('mysqli'), $ext('mbstring'), $ext('intl'), $ext('json'), $ext('fileinfo'), $ext('gd'), $ext('zip'), $ext('dom'), $ext('xml'),
        ['ok' => is_writable(__DIR__), 'label' => 'قابل نوشتن: ' . __DIR__],
        ['ok' => is_writable($root) || is_writable(dirname($root)), 'label' => 'قابل نوشتن ریشهٔ پروژه: ' . $root],
        ['ok' => $sp !== null, 'label' => $sp !== null ? 'schema.sql پیدا شد: ' . basename(dirname($sp)) . '/' . basename($sp) : 'schema.sql پیدا نشد — کنار install.php یا در install/ کنارش'],
    ];
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
    global $LOCK;
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/') . '/';
    try {
        if ($dbName === '' || $dbUser === '' || strlen($adminPass) < 12) throw new RuntimeException('همهٔ فیلدها الزامی است و رمز مدیر حداقل ۱۲ نویسه باشد.');
        $schemaFile = schema_path();
        if ($schemaFile === null) {
            throw new RuntimeException('install/schema.sql پیدا نشد. آن را یکی از این دو مسیر قرار دهید: '
                . __DIR__ . '/install/schema.sql یا کنار خود install.php در ' . __DIR__ . '/schema.sql (سطح دسترسی ۶۴۴).');
        }
        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('SET NAMES utf8mb4, FOREIGN_KEY_CHECKS = 0');
        $sql = file_get_contents($schemaFile);
        if (!is_string($sql) || trim($sql) === '') throw new RuntimeException('schema.sql خوانده نشد — سطح دسترسی فایل را ۶۴۴ کنید.');
        foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $stmt) {
            if ($stmt !== '' && !str_starts_with($stmt, '--')) $pdo->exec($stmt);
        }
        $pdo->prepare('INSERT INTO users (username, display_name, password_hash, is_admin, is_active, must_change_password, created_at) VALUES ("admin", "مدیر سیستم", ?, 1, 1, 0, UTC_TIMESTAMP())')
            ->execute([password_hash($adminPass, PASSWORD_ARGON2ID)]);

        $root = project_root();
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
        if (@file_put_contents($root . '/.env', $env) === false) throw new RuntimeException("نوشتن .env در ریشهٔ پروژه ($root) ممکن نشد — دسترسی پوشه را ۷۵۵ کنید.");

        foreach (['writable', 'writable/cache', 'writable/logs', 'writable/session', 'writable/uploads', 'writable/debugbar'] as $d) @mkdir($root . '/' . $d, 0755, true);
        fix_public_index($root);
        file_put_contents($LOCK, date('c'));

        $instUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'your-domain') . '/';
        render_page('نصب کامل شد ✓', '<div class="ok"><b>آزمون‌یار با موفقیت نصب شد!</b><br>ریشهٔ پروژه: <code>' . h($root) . '</code><br>ورود: <code>' . h($baseUrl ?: $instUrl) . '</code> با نام کاربری <code>admin</code>.<br><br>⚠ برای امنیت، فایل <code>install.php</code> و کل پوشهٔ <code>install</code> کنارش را از هاست حذف کنید.</div>', true);
    } catch (Throwable $e) {
        render_page('خطا در نصب', '<div class="err">' . h($e->getMessage()) . '<br><br><a href="install.php">تلاش مجدد</a></div>');
    }
}

/** اگر index.php عمومی به app مسیر معتبری ندارد، به ریشهٔ پروژه وصلش می‌کند */
function fix_public_index(string $root): void
{
    $idx = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/index.php';
    if ($idx === '/index.php' || !is_file($idx)) return;
    $content = @file_get_contents($idx);
    if (!is_string($content) || !str_contains($content, 'Paths.php')) return;
    if (preg_match("#require FCPATH \.\s*'([^']*Paths\.php)'#", $content, $m)) {
        $p = $m[1];
        if (str_starts_with($p, '../')) $p = dirname($idx) . '/' . $p;
        elseif (!str_starts_with($p, '/')) $p = dirname($idx) . '/' . $p;
        if (is_file($p)) return; // مسیر فعلی سالم است
    }
    $target = str_replace('\\', '/', $root) . '/app/Config/Paths.php';
    if (is_file($target)) {
        $fixed = preg_replace("#require FCPATH \.\s*'[^']*Paths\.php';#", "require FCPATH . '" . $target . "';", $content, 1);
        if ($fixed) @file_put_contents($idx, $fixed);
    }
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
code{background:#f1f4fa;border-radius:6px;padding:1px 7px;font-size:12px;direction:ltr;display:inline-block}
</style></head><body><div class="wrap">
<h1>✓ نصب‌کنندهٔ آزمون‌یار</h1><p class="sub">نصب یک‌بارمصرف روی هاست cPanel — بدون SSH و Composer</p>
<?= $body ?>
</div></body></html><?php
    if ($done) exit;
}
