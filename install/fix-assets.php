<?php
/**
 * تشخیص و تعمیر فایل‌های استاتیک (استایل) — در public_html اجرا کنید: /fix-assets.php
 *  ۱) گزارش دقیق وضعیت فایل‌ها و .htaccess
 *  ۲) اگر assets منتقل نشده، از public/assets می‌سازدش
 *  ۳) دسترسی‌ها را 755/644 می‌کند
 */
declare(strict_types=1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
$root = __DIR__;

function rrchmod(string $p, int $d = 0755, int $f = 0644): void
{
    if (!is_dir($p)) { @chmod($p, $f); return; }
    @chmod($p, $d);
    foreach (scandir($p) ?: [] as $i) {
        if ($i === '.' || $i === '..') continue;
        rrchmod("$p/$i", $d, $f);
    }
}
function rrcopy(string $src, string $dst): bool
{
    if (is_file($src)) return @copy($src, $dst);
    if (!is_dir($src)) return false;
    @mkdir($dst, 0755, true);
    foreach (scandir($src) ?: [] as $i) {
        if ($i === '.' || $i === '..') continue;
        rrcopy("$src/$i", "$dst/$i");
    }
    return true;
}

echo '<!doctype html><html lang="fa" dir="rtl"><body style="font:14px/2 Tahoma;background:#141f47;color:#fff;padding:30px"><div style="max-width:780px;margin:auto;background:#fff;color:#1c2947;border-radius:14px;padding:24px"><h2>🎨 تشخیص/تعمیر فایل‌های استاتیک</h2><pre style="white-space:pre-wrap;line-height:2">';

echo "PHP: ", PHP_VERSION, " | SAPI: ", PHP_SAPI, "\n\n";

// ۱) گزارش وضعیت کلیدی
$keys = ['index.php', 'assets', 'assets/css/theme.css', 'assets/js/app.js', 'assets/fonts/Vazirmatn-Regular.woff2', 'favicon.ico', 'robots.txt', 'public', 'public/assets', '.htaccess'];
echo "── وضعیت فایل‌ها در public_html ──\n";
foreach ($keys as $k) {
    $p = "$root/$k";
    $st = is_dir($p) ? 'DIR ' : (is_file($p) ? 'FILE' : 'هیچ');
    $perm = is_dir($p) || is_file($p) ? substr(sprintf('%o', @fileperms($p)), -4) : '—';
    $size = is_file($p) ? filesize($p) . 'b' : '';
    printf("%-40s %s  %s  %s  %s\n", $k, $st, $perm, $size, is_readable($p) ? '' : '⛔خواندهنمیشود');
}

// ۲) تعمیر: assets از public/assets
echo "\n── تعمیر ──\n";
if (!is_dir("$root/assets") && is_dir("$root/public/assets")) {
    echo "assets وجود ندارد؛ از public/assets کپی می‌کنم…\n";
    echo rrcopy("$root/public/assets", "$root/assets") ? "✓ assets ساخته شد\n" : "✗ کپی ناموفق\n";
} elseif (is_dir("$root/assets")) {
    echo "✓ پوشهٔ assets موجود است\n";
} else {
    echo "✗ نه assets هست و نه public/assets — فایل‌های assets در هاست نیستند!\n";
}
// فایل‌های ریشه که شاید جا مانده باشند
foreach (['favicon.ico', 'robots.txt'] as $f) {
    if (!is_file("$root/$f") && is_file("$root/public/$f")) { @copy("$root/public/$f", "$root/$f"); echo "✓ $f کپی شد\n"; }
}
if (is_dir("$root/assets")) { rrchmod("$root/assets"); echo "✓ دسترسی assets تنظیم شد (755/644)\n"; }
foreach (['index.php', '.htaccess'] as $f) if (is_file("$root/$f")) @chmod("$root/$f", 0644);

// ۳) محتوای .htaccess (برای بررسی)
echo "\n── .htaccess فعلی ──\n";
echo is_file("$root/.htaccess") ? rtrim((string) file_get_contents("$root/.htaccess")) : "(.htaccess وجود ندارد!)";
echo "\n\n── خروجی تست داخلی خواندن theme.css ──\n";
$css = "$root/assets/css/theme.css";
echo is_file($css) && is_readable($css) ? ('✓ خواندنی، ' . filesize($css) . ' بایت، ابتدای فایل: ' . PHP_EOL . substr((string) file_get_contents($css), 0, 80)) : '✗ خواندنی نیست!';
echo "\n\nپس از رفع، سایت را با Ctrl+F5 باز کنید. اگر assets موجود و خواندنی بود ولی مرورگر باز هم استایل نداشت، مشکل از فایروال/Mo‌dSecurity هاست است (در cPanel بخش Security → ModSecurity را برای دامنه خاموش کنید یا از پشتیبانی بخواهید قواعد css/js/woff را استثنا کنند).";
echo '</pre></div></body></html>';
