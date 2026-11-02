<?php
/** ساخت بستهٔ انتشار آزمون‌یار با ZipArchive — خروجی کاملاً استاندارد با مسیرهای یونیکس
 *  توسط deploy/make-release.bat صدا زده می‌شود (stage از قبل با robocopy آماده است). */
$stage = $argv[1] ?? (getenv('TEMP') . '/azmoon-release-stage');
$out = $argv[2] ?? (getcwd() . '/azmoon-yar-release.zip');
if (!is_dir($stage)) { fwrite(STDERR, "stage پیدا نشد: $stage\n"); exit(1); }
@unlink($out);

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { fwrite(STDERR, "ساخت ZIP ناموفق\n"); exit(1); }

// پوشه‌های خالی لازم روی هاست به‌صورت صریح اضافه شوند
foreach (['writable', 'writable/cache', 'writable/logs', 'writable/session', 'writable/uploads', 'writable/debugbar'] as $d) {
    if (is_dir("$stage/$d")) $zip->addEmptyDir($d);
}

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
$n = 0;
foreach ($it as $file) {
    $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($stage))), '/');
    $zip->addFile($file->getPathname(), $rel);
    $n++;
}
$zip->close();

$mb = round(filesize($out) / 1048576, 1);
echo "OK: $n files, $mb MB\n";
echo $out . "\n";
