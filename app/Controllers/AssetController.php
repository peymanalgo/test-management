<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * سرو فایل‌های استاتیک از طریق PHP — وقتی هاست‌های اشتراکی فایل‌های css/js/font را
 * در سطح وب‌سرور/Mo‌dSecurity مسدود می‌کنند (403)، این کنترلر همان فایل‌ها را با
 * MIME درست و کش از مسیر امن public/assets می‌خواند و تحویل می‌دهد.
 *
 * مسیر: /assets/{css|js|fonts|images}/{file} → AssetController::serve(...)
 */
class AssetController extends Controller
{
    private const MIME = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'ttf' => 'font/ttf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
    ];

    private const TYPES = ['css', 'js', 'fonts', 'images'];

    public function serve(string $type, string $file)
    {
        if (!in_array($type, self::TYPES, true)) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        // جلوگیری از directory traversal
        if (str_contains($file, '..') || str_contains($file, '\\') || str_contains($file, "\0")) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $path = FCPATH . 'assets/' . $type . '/' . $file;
        if (!is_file($path)) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = self::MIME[$ext] ?? 'application/octet-stream';

        // کش مرورگر (۷ روز)
        $this->response->setHeader('Content-Type', $mime);
        $this->response->setHeader('Cache-Control', 'public, max-age=604800');
        $this->response->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT');

        return $this->response->setBody(file_get_contents($path));
    }
}
