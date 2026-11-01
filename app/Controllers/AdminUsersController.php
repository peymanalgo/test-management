<?php

namespace App\Controllers;


class AdminUsersController extends BaseController
{
    public function index()
    {
        if (!session('user_id') || !session('is_admin')) return $this->response->setStatusCode(403);
        $db = db_connect();
        if (strtolower($this->request->getMethod()) === 'post') {
            $username = trim((string) $this->request->getPost('username'));
            $password = (string) $this->request->getPost('password');
            $name = trim((string) $this->request->getPost('display_name'));
            $roleId = (int) $this->request->getPost('role_id');
            if (!$username || !$name || strlen($password) < 12 || !$roleId) return redirect()->back()->with('error','همهٔ فیلدها الزامی‌اند؛ رمز حداقل ۱۲ کاراکتر باشد.');
            if ($db->table('users')->where('username',$username)->countAllResults()) return redirect()->back()->with('error','این نام کاربری تکراری است.');
            $db->transStart();
            $db->table('users')->insert(['username'=>$username,'display_name'=>$name,'email'=>trim((string)$this->request->getPost('email'))?:null,'password_hash'=>password_hash($password,PASSWORD_ARGON2ID),'is_active'=>1,'is_admin'=>$this->request->getPost('is_admin')?1:0,'must_change_password'=>1,'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]);
            $userId=$db->insertID(); $db->table('user_roles')->insert(['user_id'=>$userId,'role_id'=>$roleId]); $db->transComplete();
            return redirect()->to('/admin/users')->with('message','کاربر ایجاد شد؛ در اولین ورود باید رمز خود را تغییر دهد.');
        }
        return view('admin/users',['users'=>$db->query('SELECT u.id,u.username,u.display_name,u.email,u.is_active,u.is_admin,r.name role_name FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id WHERE u.deleted_at IS NULL ORDER BY u.id DESC')->getResultArray(),'roles'=>$db->table('roles')->get()->getResultArray()]);
    }
}
