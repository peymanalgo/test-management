<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * آپدیت خودکار سامانه از گیت‌هاب — فقط ادمین.
 * از CDN jsdelivr (در ایران در دسترس) فایل‌ها را می‌گیرد و اعمال می‌کند.
 */
class UpdateController extends Controller
{
    private const REPO = 'peymanalgo/test-management';
    private const BRANCH = 'main';
    private const CDN = 'https://cdn.jsdelivr.net/gh/';
    private const API = 'https://data.jsdelivr.com/v1/packages/gh/';

    /** مسیرهایی که آپدیت می‌شوند (فقط کد — نه .env و writable و vendor) */
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
        $localVersion = $this->localVersion();
        return view('admin/update', ['localVersion' => $localVersion]);
    }

    /** AJAX: بررسی آخرین نسخه از CDN */
    public function check()
    {
        if (!session('is_admin')) return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'دسترسی مجاز نیست.']);
        // cache-bust برای دور زدن کَش CDN
        $remote = $this->fetch(self::CDN . self::REPO . '@' . self::BRANCH . '/VERSION?t=' . time());
        if ($remote === null) {
            // بدون cache-bust
            $remote = $this->fetch(self::CDN . self::REPO . '@' . self::BRANCH . '/VERSION');
        }
        if ($remote === null) {
            return $this->response->setJSON([
                'ok' => false,
                'message' => 'اتصال به سرور آپدیت ممکن نشد.',
                'local' => $this->localVersion(),
            ]);
        }
        $remoteVersion = $this->cleanVersion($remote);
        $localVersion = $this->localVersion();
        $hasUpdate = $remoteVersion !== $localVersion;
        return $this->response->setJSON([
            'ok' => true,
            'hasUpdate' => $hasUpdate,
            'local' => $localVersion,
            'remote' => $remoteVersion,
            'message' => $hasUpdate ? 'نسخهٔ جدیدی در دسترس است.' : 'شما آخرین نسخه را دارید.',
        ]);
    }

    /** AJAX: دریافت و اعمال آپدیت */
    public function apply()
    {
        if (!session('is_admin')) return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'دسترسی مجاز نیست.']);
        $maxTime = ini_get('max_execution_time');
        set_time_limit(300); // ۵ دقیقه

        $root = FCPATH;
        $updated = 0;
        $errors = [];
        $skipped = 0;

        // ۱) فهرست فایل‌ها از jsdelivr Data API
        $fileList = $this->fetch(self::API . self::REPO . '@' . self::BRANCH . '?structure=flat');
        if ($fileList === null) {
            return $this->response->setJSON(['ok' => false, 'message' => 'دریافت فهرست فایل‌ها از CDN ممکن نشد.']);
        }
        $data = json_decode($fileList, true);
        $files = $data['files'] ?? [];
        if (empty($files)) {
            return $this->response->setJSON(['ok' => false, 'message' => 'فهرست فایل‌ها خالی است.']);
        }

        // ۲) فیلتر و دانلود
        foreach ($files as $file) {
            $path = $file['name'] ?? '';
            if ($path === '' || $path === '/') continue;

            // فقط مسیرهای مجاز
            $allowed = false;
            foreach (self::UPDATE_PATHS as $prefix) {
                if (str_starts_with($path, $prefix)) { $allowed = true; break; }
            }
            if (!$allowed) { $skipped++; continue; }

            // دانلود از CDN
            $content = $this->fetch(self::CDN . self::REPO . '@' . self::BRANCH . '/' . $path);
            if ($content === null) { $errors[] = "خطا در دریافت: $path"; continue; }

            // ذخیره در مسیر اصلی
            $target = $root . '/' . $path;
            $dir = dirname($target);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (@file_put_contents($target, $content) === false) {
                $errors[] = "خطا در نوشتن: $path";
            } else {
                $updated++;
            }

            // اگر فایل از public/assets است، در assets/ هم ذخیره کن (برای هاست‌هایی که public را flat کردند)
            if (str_starts_with($path, 'public/assets/')) {
                $altPath = substr($path, 7); // حذف 'public/'
                $altTarget = $root . '/' . $altPath;
                $altDir = dirname($altTarget);
                if (!is_dir($altDir)) @mkdir($altDir, 0755, true);
                @file_put_contents($altTarget, $content);
            }
        }

        // ۳) به‌روزرسانی VERSION
        $remoteVersion = $this->fetch(self::CDN . self::REPO . '@' . self::BRANCH . '/VERSION?t=' . time());
        if ($remoteVersion === null) $remoteVersion = $this->fetch(self::CDN . self::REPO . '@' . self::BRANCH . '/VERSION');
        if ($remoteVersion !== null) @file_put_contents($root . '/VERSION', $this->cleanVersion($remoteVersion));

        $msg = "آپدیت کامل شد: $updated فایل به‌روزرسانی، $skipped فایل رد شد.";
        if (!empty($errors)) $msg .= ' خطاها: ' . implode('؛ ', array_slice($errors, 0, 5));

        return $this->response->setJSON([
            'ok' => empty($errors) || $updated > 0,
            'message' => $msg,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    private function cleanVersion(string $raw): string
    {
        $v = trim($raw);
        if (str_starts_with($v, 'VERSION=')) $v = trim(substr($v, 8));
        return $v;
    }

    private function localVersion(): string
    {
        $v = @file_get_contents(FCPATH . 'VERSION');
        return $v !== false ? $this->cleanVersion($v) : 'unknown';
    }

    private function fetch(string $url): ?string
    {
        // روش ۱: allow_url_fopen
        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'AzmoonYar-Updater/1.0']]);
            $result = @file_get_contents($url, false, $ctx);
            if ($result !== false) return $result;
        }
        // روش ۲: cURL
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
