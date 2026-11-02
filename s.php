<?php
// سرو فایل‌های استاتیک از طریق PHP — برای هاست‌هایی که css/js/font را 403 می‌کنند
$f = $_GET['f'] ?? '';
if ($f === '' || strpos($f, '..') !== false || strpos($f, "\\") !== false) { http_response_code(404); exit; }
$path = __DIR__ . '/assets/' . ltrim($f, '/');
if (!is_file($path)) { http_response_code(404); exit; }
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$mimes = [
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
];
header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($path));
readfile($path);
