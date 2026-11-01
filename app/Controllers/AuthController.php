<?php

namespace App\Controllers;

use App\Models\UserModel;

class AuthController extends BaseController
{
    public function login()
    {
        if (session('user_id')) return $this->redirectByRole();
        if (strtolower($this->request->getMethod()) === 'post') {
            // محدودسازی تلاش ناموفق: حداکثر ۵ تلاش در هر ۵ دقیقه
            $fails = (int) (session('login_fails') ?? 0);
            $firstFail = session('login_first_fail');
            if ($fails >= 5 && $firstFail && (time() - (int) $firstFail) < 300) {
                return redirect()->back()->withInput()->with('error', t('login.rate_limited'));
            }
            if ($fails === 0 || (time() - (int) $firstFail) >= 300) {
                session()->set('login_first_fail', time());
                session()->set('login_fails', 0);
            }
            $user = (new UserModel())->findByUsername(trim((string) $this->request->getPost('username')));
            $ok = $user && (int) $user['is_active'] === 1 && password_verify((string) $this->request->getPost('password'), $user['password_hash']);
            if (!$ok) {
                session()->set('login_fails', $fails + 1);
                return redirect()->back()->withInput()->with('error', t('login.wrong'));
            }
            if ((int) $user['must_change_password'] === 1) {
                // در این نسخه فیلد اجباری تغییر رمز فقط اطلاع‌رسانی می‌کند؛ رمز از .env مدیریت می‌شود
            }
            session()->regenerate();
            session()->remove(['login_fails', 'login_first_fail']);
            $roles = db_connect()->table('user_roles ur')->select('r.code')->join('roles r', 'r.id = ur.role_id')->where('ur.user_id', $user['id'])->get()->getResultArray();
            session()->set([
                'user_id' => (int) $user['id'],
                'display_name' => $user['display_name'],
                'is_admin' => (bool) $user['is_admin'],
                'role_code' => $roles[0]['code'] ?? null,
            ]);
            db_connect()->table('users')->update(['last_login_at' => gmdate('Y-m-d H:i:s')], ['id' => $user['id']]);
            return $this->redirectByRole();
        }
        return view('auth/login');
    }

    private function redirectByRole()
    {
        return redirect()->to(session('is_admin') ? '/dashboard' : '/workbench');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
