<?php
/**
 * پچ inline برای هاست‌هایی که css/js/font را 403 می‌کنند.
 * این فایل را در public_html اجرا کنید: php patch-inline.php
 * تمام <link href="/assets/..."> و <script src="/assets/..."> را به inline تبدیل می‌کند.
 */
$root = __DIR__;
$files = glob("$root/app/Views/layouts/*.php") ?: [];
$files = array_merge($files, glob("$root/app/Views/auth/*.php") ?: []);
$total = 0;

foreach ($files as $file) {
    $c = file_get_contents($file);
    if ($c === false || !str_contains($c, '/assets/')) continue;

    // CSS → inline
    $c = preg_replace_callback(
        '#<link rel="stylesheet" href="/assets/css/([^"]+)"[^>]*>#',
        function ($m) use ($root) {
            $css = @file_get_contents("$root/assets/css/" . $m[1]);
            if ($css === false) return $m[0];
            // فونت را base64 کن
            $css = preg_replace_callback(
                "#url\('/assets/fonts/([^']+)'\)#",
                function ($f) use ($root) {
                    $data = @file_get_contents("$root/assets/fonts/" . $f[1]);
                    if ($data === false) return $f[0];
                    $ext = pathinfo($f[1], PATHINFO_EXTENSION);
                    $mime = ['woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf'][$ext] ?? 'font/woff2';
                    return "url('data:{$mime};base64," . base64_encode($data) . "')";
                },
                $css
            );
            return "<style>\n" . $css . "\n</style>";
        },
        $c
    );

    // JS → inline
    $c = preg_replace_callback(
        '#<script src="/assets/js/([^"]+)"[^>]*></script>#',
        function ($m) use ($root) {
            $js = @file_get_contents("$root/assets/js/" . $m[1]);
            if ($js === false) return $m[0];
            return "<script>\n" . $js . "\n</script>";
        },
        $c
    );

    // SVG login mesh → inline
    $c = preg_replace_callback(
        "#url\('/assets/images/([^']+)'\)#",
        function ($m) use ($root) {
            $data = @file_get_contents("$root/assets/images/" . $m[1]);
            if ($data === false) return $m[0];
            return "url('data:image/svg+xml;base64," . base64_encode($data) . "')";
        },
        $c
    );

    if (file_put_contents($file, $c) !== false) {
        echo "PATCHED: " . basename($file) . "\n";
        $total++;
    }
}
echo "DONE: $total files patched\n";
echo "Now test: curl -sI http://test-management.ir/login | head -1\n";
