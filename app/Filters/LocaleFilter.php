<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** تعیین زبان هر درخواست از سشن/کوکی — fa (پیش‌فرض) یا en */
class LocaleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $lang = session()->get('lang') ?: ($_COOKIE['lang'] ?? null);
        if (!in_array($lang, ['fa', 'en'], true)) $lang = 'fa';
        service('language')->setLocale($lang);
        if (session()->get('lang') !== $lang) session()->set('lang', $lang);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
