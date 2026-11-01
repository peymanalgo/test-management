<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * کنترل دسترسی سراسری:
 *  - کاربر مهمان → هدایت به ورود (درخواست AJAX پاسخ 401 JSON می‌گیرد)
 *  - ادمین → همه منوها به‌جز کارتابل (ادمین کارتابل ندارد)
 *  - کاربر عادی → فقط و فقط کارتابل
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $path = trim((string) service('request')->getUri()->getPath(), '/');
        $path = preg_replace('#^index\.php/?#', '', $path);
        $isAjax = service('request')->hasHeader('X-Requested-With') || str_contains(service('request')->getHeaderLine('Accept'), 'application/json');

        if (!session('user_id')) {
            if ($isAjax) {
                return service('response')->setStatusCode(401)->setJSON(['ok' => false, 'message' => 'نشست شما منقضی شده است؛ دوباره وارد شوید.']);
            }
            return redirect()->to('/login');
        }

        $onWorkbench = $path === 'workbench' || str_starts_with($path, 'workbench/');

        if (session('is_admin')) {
            if ($onWorkbench) return redirect()->to('/dashboard');
            return;
        }

        // کاربر عادی: تنها کارتابل در دسترس است (خروج، اعلان، اقدام‌های خطا و تاریخچهٔ سناریوهای خودش مشترک است)
        $allowed = $onWorkbench
            || $path === 'logout' || str_starts_with($path, 'logout/')
            || $path === 'notifications' || str_starts_with($path, 'notifications/')
            || preg_match('#^defects/\d+/fix-(start|done)$#', $path)
            || preg_match('#^history/\d+$#', $path);
        if ($allowed) return;

        if ($isAjax) {
            return service('response')->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'دسترسی به این بخش برای نقش شما مجاز نیست.']);
        }
        return redirect()->to('/workbench');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
