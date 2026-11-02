<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * آپدیت خودکار سامانه از گیت‌هاب — فقط ادمین.
 * برای دور زدن کَش CDN (@main تا ۱۲ ساعت کَش می‌شود)،
 * اول hash آخرین کامیت را از Data API می‌گیرد و با آن دانلود می‌کند.
 */
class UpdateController extends Controller
{
    private const REPO = 'peymanalgo/test-management';
    private const CDN = 'https://cdn.jsdelivr.net/gh/';
    private const API = 'https://data.jsdelivr.com/v1/packages/gh/';
    private const FLAT_API = 'https://data.jsdelivr.com/v1/packages/gh/';

    private const UPDATE_PATHS = [
        'app/Controllers/',
        'app/Views/',
        'app/Config/',
        'app/Language/',
        'app/Services/',
        'app/Models/',
        'app/Filters/',
        'app/Common.php',
        'public/assets/',
        'VERSION',
    ];

    public function index()
    {
        if (!session('is_admin')) return redirect()->to('/workbench');
        return view('admin/update', ['localVersion' => $this->localVersion()]);
    }

    /** AJAX: بررسی — ساده و بدون وابستگی به API بیرونی */
    public function check()
    {
        if (!session('is_admin')) return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        $local = $this->localVersion();
        // تلاش برای دریافت VERSION از CDN — فقط اطلاعاتی، نه شرط
        $remote = $this->fetchFile('VERSION');
        $remoteVersion = $remote !== null ? trim($remote) : null;
        return $this->response->setJSON([
            'ok' => true,
            'hasUpdate' => true,
            'local' => $local,
            'remote' => $remoteVersion ?: 'در دسترس',
            'commit' => $remoteVersion,
            'message' => 'برای دریافت آخرین تغییرات، دکمهٔ آپدیت را بزنید.',
        ]);
    }

    /** AJAX: دریافت و اعمال آپدیت */
    public function apply()
    {
        if (!session('is_admin')) return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        set_time_limit(300);
        $root = FCPATH;
        $updated = 0;
        $errors = [];
        $skipped = 0;

        // ۱) آخرین کامیت — دور زدن کَش @main
        $commit = $this->getLatestCommit();
        $ref = $commit ?: 'main'; // fallback
        $base = self::CDN . self::REPO . '@' . $ref . '/';

        // ۲) فهرست فایل‌ها
        $fileList = $this->fetch(self::FLAT_API . self::REPO . '@' . $ref . '?structure=flat');
        if ($fileList === null) {
            return $this->response->setJSON(['ok' => false, 'message' => 'دریافت فهرست فایل‌ها ممکن نشد.']);
        }
        $data = json_decode($fileList, true);
        $files = $data['files'] ?? [];
        if (empty($files)) {
            return $this->response->setJSON(['ok' => false, 'message' => 'فهرست فایل‌ها خالی است.']);
        }

        // ۳) دانلود و ذخیره
        foreach ($files as $file) {
            $path = $file['name'] ?? '';
            // حذف / ابتدایی که Data API اضافه می‌کند
            $path = ltrim($path, '/');
            if ($path === '' || $path === '/') continue;

            $allowed = false;
            foreach (self::UPDATE_PATHS as $prefix) {
                if (str_starts_with($path, $prefix)) { $allowed = true; break; }
            }
            if (!$allowed) { $skipped++; continue; }

            $content = $this->fetchFile($path);
            if ($content === null) { $errors[] = "خطا: $path"; continue; }

            // ذخیره در مسیر اصلی
            $target = $root . '/' . $path;
            $dir = dirname($target);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (@file_put_contents($target, $content) !== false) $updated++;
            else $errors[] = "نوشتن نشد: $path";

            // برای هاست‌هایی که public را flat کردند (public/assets → assets)
            if (str_starts_with($path, 'public/assets/')) {
                $alt = $root . '/' . substr($path, 7);
                @mkdir(dirname($alt), 0755, true);
                @file_put_contents($alt, $content);
            }
        }

        // ۴) ذخیرهٔ commit hash به‌عنوان نسخهٔ محلی
        if ($commit) @file_put_contents($root . '/VERSION', $commit);

        $msg = "آپدیت کامل شد: $updated فایل به‌روزرسانی.";
        if (!empty($errors)) $msg .= ' (' . count($errors) . ' خطا)';
        return $this->response->setJSON([
            'ok' => $updated > 0,
            'message' => $msg,
            'updated' => $updated,
            'commit' => $commit,
        ]);
    }

    /** hash آخرین کامیت را از Data API می‌گیرد */
    private function getLatestCommit(): ?string
    {
        static $commit = null;
        if ($commit !== null) return $commit;
        $json = $this->fetch(self::FLAT_API . self::REPO . '/resolved?specifier=main');
        if ($json) {
            $data = json_decode($json, true);
            $commit = $data['version'] ?? null;
        }
        return $commit;
    }

    /** دانلود از چند منبع — اولین منبع تازه برمی‌گرداند */
    private function fetchFile(string $path): ?string
    {
        // منبع ۱: Statically CDN (کَش کمتر — تازه‌ترین)
        $r = $this->fetch('https://cdn.statically.io/gh/' . self::REPO . '/main/' . $path);
        if ($r !== null) return $r;
        // منبع ۲: raw.githubusercontent (همیشه تازه، ممکن است در ایران بلاک باشد)
        $r = $this->fetch('https://raw.githubusercontent.com/' . self::REPO . '/main/' . $path);
        if ($r !== null) return $r;
        // منبع ۳: jsdelivr (قطعاً در ایران باز است ولی کَش تا ۱۲ ساعت)
        $r = $this->fetch(self::CDN . self::REPO . '@main/' . $path);
        return $r;
    }

    private function localVersion(): string
    {
        $v = @file_get_contents(FCPATH . 'VERSION');
        return $v !== false ? trim($v) : 'unknown';
    }

    private function fetch(string $url): ?string
    {
        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'AzmoonYar-Updater/1.0']]);
            $result = @file_get_contents($url, false, $ctx);
            if ($result !== false) return $result;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'AzmoonYar-Updater/1.0',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $result = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 200 && $result !== false) return $result;
        }
        return null;
    }
}
