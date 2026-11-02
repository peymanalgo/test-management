<?php
/** یک‌بارمصرف: اعتبارسنجی نهایی بستهٔ انتشار با ZipArchive (پارسر استاندارد PHP همانند cPanel) */
$z = new ZipArchive();
if ($z->open('azmoon-yar-release.zip') !== true) { echo "OPEN FAILED\n"; exit(1); }
$need = ['vendor/autoload.php', 'vendor/codeigniter4/framework/composer.json', 'install/install.php', 'install/schema.sql', 'public/index.php', 'public/.htaccess', 'app/Config/App.php', 'public/assets/css/theme.css', 'public/assets/fonts/Vazirmatn-Regular.woff2'];
$count = $z->numFiles;
$found = 0;
foreach ($need as $n) { $ok = $z->locateName($n) !== false; $found += $ok ? 1 : 0; if (!$ok) echo "MISSING: $n\n"; }
$env = $z->locateName('.env') !== false;
$back = 0;
for ($i = 0; $i < $count; $i++) if (strpos($z->getNameIndex($i), '\\') !== false) $back++;
$z->close();
echo "entries=$count | لازم=$found/" . count($need) . " | env=" . ($env ? 'HAVEDANGER' : 'ندارد✓') . " | backslash=$back\n";
echo ($found === count($need) && !$env && $back === 0) ? "بسته سالم و آمادهٔ آپلود ✓\n" : "بسته مشکل دارد!\n";
