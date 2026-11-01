<?php

namespace App\Controllers;

/** تغییر زبان رابط: /lang/fa یا /lang/en — سشن + کوکی ست می‌شود و به صفحهٔ قبل برمی‌گردد */
class LangController extends BaseController
{
    public function switch(string $lang)
    {
        if (!in_array($lang, ['fa', 'en'], true)) $lang = 'fa';
        session()->set('lang', $lang);
        setcookie('lang', $lang, time() + 31536000, '/');
        $ref = (string) ($this->request->getServer('HTTP_REFERER') ?? '');
        $base = (string) config('App')->baseURL;
        // فقط ارجاع به میزبان خودمان پذیرفته می‌شود
        $target = ($ref !== '' && str_starts_with($ref, rtrim($base, '/'))) ? $ref : rtrim($base, '/') . '/';
        return redirect()->to($target);
    }
}
