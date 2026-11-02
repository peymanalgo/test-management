<?php
/**
 * اصلاح چیدمان پس از نصب — یک‌بارمصرف، مخصوص حالتی که کل پروژه داخل public_html اکسترکت شده است:
 *   ۱) محتوای public/ را به ریشهٔ public_html منتقل می‌کند (index.php ، .htaccess ، assets)
 *   ۲) .htaccess امنیتی (ری‌رایت + مسدودسازی app/vendor/writable/.env) می‌نویسد
 *   ۳) اگر دیتابیس نصب‌شده باشد، نصب‌کننده و پوشهٔ install را حذف می‌کند
 *   ۴) خودش را حذف می‌کند
 * اجرا: این فایل را در public_html بگذارید و در مرورگر باز کنید: /fix-layout.php
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="fa" dir="rtl"><body style="font:14px/2 Tahoma;background:#141f47;color:#fff;padding:30px"><div style="max-width:720px;margin:auto;background:#fff;color:#1c2947;border-radius:14px;padding:24px"><h2>🔧 اصلاح چیدمان آزمون‌یار</h2><pre style="white-space:pre-wrap;line-height:2">';
ob_start();

$root = __DIR__;
$done = static function (string $m): void { echo '✓ ', $m, "\n"; };
$fail = static function (string $m): never { echo '✗ ', $m, "\n"; echo '</pre><p>پس از رفع مشکل، صفحه را رفرش کنید.</p></div></body></html>'; exit; };

// ۱) انتقال محتوای public به ریشه
$public = $root . '/public';
if (!is_dir($public)) $fail('پوشهٔ public کنار این فایل نیست — چیدمان موردنظر نیست؛ نیازی به این اسکریپت ندارید.');
foreach (scandir($public) ?: [] as $item) {
    if ($item === '.' || $item === '..') continue;
    $src = "$public/$item";
    $dst = "$root/$item";
    if (file_exists($dst)) { echo "– از قبل موجود، رد شد: $item\n"; continue; }
    if (!@rename($src, $dst)) $fail("انتقال $item ناموفق — دسترسی File Manager را بررسی کنید.");
    $done("منتقل شد: public/$item → $item");
}

// ۲) .htaccess امنیتی + ری‌رایت
$ht = <<<HT
# آزمون‌یار — ری‌رایت + امنیت
Options -Indexes
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} ^/?$
    RewriteRule ^$ index.php [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php/$1 [L]
</IfModule>
# مسدودسازی مسیرهای حساس (دفاع در عمق)
<FilesMatch "^\.env">
    Require all denied
</FilesMatch>
<IfModule mod_rewrite.c>
    RewriteRule ^(app|vendor|writable|install|bin|deploy|tests|docs)(/|$) - [F,L]
</IfModule>
HT;
if (@file_put_contents($root . '/.htaccess', $ht) === false) $fail('نوشتن .htaccess ممکن نشد.');
$done('.htaccess امنیتی نوشته شد (ری‌رایت + مسدودسازی app/vendor/writable)');

// ۳) حذف امن نصب‌کننده (فقط اگر نصب واقعاً انجام شده باشد)
$installed = false;
if (is_file($root . '/.env') && preg_match("/database\.default\.database\s*=\s*'?([^'\n]+)'?/", (string) file_get_contents($root . '/.env'), $m)) {
    $user = preg_match("/database\.default\.username\s*=\s*'?([^'\n]+)'?/", (string) file_get_contents($root . '/.env'), $u) ? trim($u[1]) : '';
    $pass = preg_match("/database\.default\.password\s*=\s*'?([^'\n]*)'?/", (string) file_get_contents($root . '/.env'), $p) ? $p[1] : '';
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=' . trim($m[1]), $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $installed = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (Throwable $e) { echo '– دیتابیس برای تأیید نصب بررسی نشد: ', $e->getMessage(), "\n"; }
}
if ($installed) {
    foreach (["$root/install", "$root/install.php", "$root/install.php.lock", "$root/install/install.php.lock"] as $t) {
        if (!file_exists($t)) continue;
        if (is_dir($t)) { shell_exec('rm -rf ' . escapeshellarg($t)); @rmdir($t); }
        else @unlink($t);
        echo file_exists($t) ? "⚠ حذف نشد، دستی حذف کنید: $t\n" : "✓ حذف شد: " . basename($t) . "\n";
    }
} else {
    echo "⚠ تأیید نصب ممکن نشد — پوشهٔ install و install.php را بعد از اطمینان از نصب، دستی حذف کنید.\n";
}

echo "\n سایت را باز کنید: index.php یا دامنهٔ اصلی\n";
@unlink(__FILE__);
echo file_exists(__FILE__) ? '⚠ این فایل (fix-layout.php) را دستی حذف کنید.' : '✓ fix-layout.php خودش حذف شد.';
echo '</pre></div></body></html>';
