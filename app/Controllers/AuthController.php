<?php

namespace App\Controllers;

use App\Models\UserModel;

class AuthController extends BaseController
{
    public function login()
    {
        if (session('user_id')) return redirect()->to('/dashboard');
        if ($this->request->getMethod() === 'POST') {
            $user = (new UserModel())->findByUsername(trim((string) $this->request->getPost('username')));
            if (!$user || !password_verify((string) $this->request->getPost('password'), $user['password_hash'])) return redirect()->back()->with('error', 'نام کاربری یا رمز عبور نادرست است.');
            session()->regenerate();
            session()->set(['user_id'=>$user['id'], 'display_name'=>$user['display_name'], 'is_admin'=>(bool) $user['is_admin']]);
            return redirect()->to('/dashboard');
        }
        return view('auth/login');
    }
    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
