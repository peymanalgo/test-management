<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * آپدیت خودکار — ساده و مطمئن.
 * بدون هیچ API بیرونی؛ فقط دانلود مستقیم فایل‌ها از Statically CDN.
 */
class UpdateController extends Controller
{
    private const REPO = 'peymanalgo/test-management';
    /** CDN اصلی — Statically (کَش کمتر، در ایران در دسترس) */
    private const CDN_STAT = 'https://cdn.statically.io/gh/';
    /** CDN جایگزین — raw.githubusercontent (همیشه تازه، ممکن است بلاک باشد) */
    private const CDN_RAW = 'https://raw.githubusercontent.com/';
    /** CDN سوم — jsdelivr (در ایران باز، کَش زیاد) */
    private const CDN_JSD = 'https://cdn.jsdelivr.net/gh/';

    /** فایل‌هایی که آپدیت می‌شوند — hardcoded، بدون API */
    private const FILES = [
        // Controllers
        'app/Controllers/AuthController.php',
        'app/Controllers/AssetController.php',
        'app/Controllers/BaseController.php',
        'app/Controllers/DashboardController.php',
        'app/Controllers/DefectsController.php',
        'app/Controllers/Home.php',
        'app/Controllers/LangController.php',
        'app/Controllers/NotificationsController.php',
        'app/Controllers/ProjectsController.php',
        'app/Controllers/TestCasesController.php',
        'app/Controllers/TestRunsController.php',
        'app/Controllers/UpdateController.php',
        'app/Controllers/WorkbenchController.php',
        // Config (فقط مواردی که تغییر می‌کنند)
        'app/Config/App.php',
        'app/Config/Filters.php',
        'app/Config/Routes.php',
        // Filters
        'app/Filters/AuthFilter.php',
        'app/Filters/LocaleFilter.php',
        // Language
        'app/Language/fa/App.php',
        'app/Language/en/App.php',
        // Models
        'app/Models/ProjectModel.php',
        'app/Models/UserModel.php',
        // Services
        'app/Services/WorkflowService.php',
        // Common
        'app/Common.php',
        // Views
        'app/Views/layouts/main.php',
        'app/Views/auth/login.php',
        'app/Views/dashboard/index.php',
        'app/Views/projects/index.php',
        'app/Views/projects/tree.php',
        'app/Views/test_cases/index.php',
        'app/Views/workbench/index.php',
        'app/Views/admin/users.php',
        'app/Views/admin/update.php',
        'app/Views/notifications/index.php',
        // Assets
        'public/assets/css/theme.css',
        'public/assets/js/app.js',
        // Version
        'VERSION',
    ];

    public function index()
    {
        if (!session('is_admin')) return redirect()->to('/workbench');
        return view('admin/update', ['localVersion' => $this->localVersion()]);
    }

    /** AJAX: بررسی — VERSION محلی را با CDN مقایسه می‌کند */
    public function check()
    {
        if (!session('is_admin')) return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        $local = $this->localVersion();
        $remote = $this->fetchFile('VERSION');
        $remoteVersion = $remote !== null ? trim($remote) : null;

        if ($remoteVersion === null) {
            return $this->response->setJSON([
                'ok' => true, 'hasUpdate' => true, 'local' => $local,
                'remote' => 'در دسترس', 'commit' => null,
                'message' => 'بررسی نسخه ممکن نبود — می‌توانید مستقیم آپدیت کنید.',
            ]);
        }
        // اگر نسخهٔ محلی جدیدتر یا برابر CDN باشد → آخرین نسخه
        // (کد لوکال همیشه جدیدتر از CDN است چون منبع push است)
        $hasUpdate = $remoteVersion > $local; // مقایسهٔ رشته‌ای ISO timestamp
        return $this->response->setJSON([
            'ok' => true, 'hasUpdate' => $hasUpdate, 'local' => $local,
            'remote' => $remoteVersion, 'commit' => $remoteVersion,
            'message' => $hasUpdate ? 'نسخهٔ جدیدی در دسترس است.' : 'شما آخرین نسخه را دارید.',
        ]);
    }

    /** AJAX: دانلود و اعمال */
    public function apply()
    {
        if (!session('is_admin')) return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        @set_time_limit(120);
        $root = FCPATH;
        $updated = 0;
        $failed = [];

        foreach (self::FILES as $path) {
            $content = $this->fetchFile($path);
            if ($content === null) { $failed[] = basename($path); continue; }

            $target = $root . '/' . $path;
            // برای فایل‌های app/ در لوکال باید یک سطح بالا برویم (FCPATH = public/)
            if (!str_starts_with($path, 'public/') && !is_dir(dirname($target))) {
                $target = dirname($root) . '/' . $path;
            }
            @mkdir(dirname($target), 0755, true);
            if (@file_put_contents($target, $content) !== false) $updated++;
            else $failed[] = basename($path);

            // برای هاست‌هایی که public/assets را به assets منتقل کرده‌اند
            if (str_starts_with($path, 'public/assets/')) {
                $alt = $root . '/' . substr($path, 7);
                @mkdir(dirname($alt), 0755, true);
                @file_put_contents($alt, $content);
            }
        }

        // VERSION را تازه کن
        $v = $this->fetchFile('VERSION');
        if ($v !== null) @file_put_contents($root . '/VERSION', trim($v));

        $msg = $updated > 0
            ? "آپدیت کامل شد: $updated فایل به‌روزرسانی شد."
            : 'هیچ فایلی آپدیت نشد — CDN در دسترس نیست.';
        if (!empty($failed) && count($failed) <= 5) $msg .= ' (ناموفق: ' . implode(', ', $failed) . ')';

        return $this->response->setJSON(['ok' => $updated > 0, 'message' => $msg, 'updated' => $updated]);
    }

    /** دانلود یک فایل — اولویت: raw.github (همیشه تازه) → Statically → jsdelivr */
    private function fetchFile(string $path): ?string
    {
        // ۱: raw.githubusercontent — همیشه آخرین کامیت، بدون کَش
        $r = $this->fetchUrl('https://raw.githubusercontent.com/' . self::REPO . '/main/' . $path);
        if ($r !== null) return $r;
        // ۲: Statically — در ایران در دسترس ولی کَش دارد
        $r = $this->fetchUrl(self::CDN_STAT . self::REPO . '/main/' . $path);
        if ($r !== null) return $r;
        // ۳: jsdelivr — fallback نهایی
        return $this->fetchUrl(self::CDN_JSD . self::REPO . '@main/' . $path);
    }

    private function localVersion(): string
    {
        // FCPATH در لوکال = public/ ، در هاست = public_html/
        // VERSION ممکن است در هر دو باشد
        foreach ([FCPATH . 'VERSION', dirname(FCPATH) . '/VERSION'] as $p) {
            $v = @file_get_contents($p);
            if ($v !== false) return trim($v);
        }
        return 'unknown';
    }

    private function fetchUrl(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'AzmoonYar/1.0',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $r = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 200 && $r !== false && $r !== '') return $r;
        }
        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'AzmoonYar/1.0']]);
            $r = @file_get_contents($url, false, $ctx);
            if ($r !== false && $r !== '') return $r;
        }
        return null;
    }
}
