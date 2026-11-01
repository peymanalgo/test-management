<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialAdminSeeder extends Seeder
{
    public function run()
    {
        $password = getenv('INITIAL_ADMIN_PASSWORD');
        if (!$password) throw new \RuntimeException('INITIAL_ADMIN_PASSWORD باید برای ایجاد مدیر اولیه تنظیم شود.');
        $db = db_connect();
        $db->table('roles')->insertBatch([['code'=>'TESTER','name'=>'آزمونگر'],['code'=>'DEVELOPER','name'=>'برنامه‌نویس'],['code'=>'MANAGER','name'=>'مدیر پروژه']]);
        $db->table('users')->insert(['username'=>'admin','display_name'=>'مدیر سیستم','password_hash'=>password_hash($password, PASSWORD_ARGON2ID),'is_admin'=>1,'is_active'=>1,'must_change_password'=>1,'created_at'=>gmdate('Y-m-d H:i:s')]);
    }
}
