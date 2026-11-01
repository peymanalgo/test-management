<?php

namespace App\Controllers;

/** مدیریت کاربران و نقش‌ها — آزمونگر / برنامه‌نویس / مدیر پروژه / مدیر سیستم */
class AdminUsersController extends BaseController
{
    public function index()
    {
        return view('admin/users');
    }

    public function data()
    {
        $db = db_connect();
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $role = (string) ($this->request->getGet('role') ?? '');
        $active = (string) ($this->request->getGet('active') ?? '');
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 20;

        $b = $db->table('users u')
            ->select('u.id, u.username, u.display_name, u.email, u.is_active, u.is_admin, u.last_login_at,
                      GROUP_CONCAT(r.name SEPARATOR "، ") roles')
            ->join('user_roles ur', 'ur.user_id = u.id', 'left')
            ->join('roles r', 'r.id = ur.role_id', 'left')
            ->where('u.deleted_at', null)
            ->groupBy('u.id, u.username, u.display_name, u.email, u.is_active, u.is_admin, u.last_login_at');
        if ($q !== '') $b->havingLike('u.display_name', $q)->orHavingLike('u.username', $q);
        if ($role !== '') $b->havingLike('roles', $role);
        if ($active !== '') $b->where('u.is_active', (int) $active);
        $rows = $b->orderBy('u.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        $total = $db->table('users')->where('deleted_at', null)->countAllResults();
        return $this->response->setJSON(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage]);
    }

    /** ایجاد/ویرایش کاربر */
    public function save()
    {
        $id = (int) ($this->request->getPost('id') ?? 0);
        $username = trim((string) $this->request->getPost('username'));
        $name = trim((string) $this->request->getPost('display_name'));
        $email = trim((string) $this->request->getPost('email'));
        $roleId = (int) ($this->request->getPost('role_id') ?? 0);
        $isAdmin = $this->request->getPost('is_admin') ? 1 : 0;
        $password = (string) $this->request->getPost('password');
        if (mb_strlen($username) < 3) return $this->jsonError(t('users.username_min'));
        if (mb_strlen($name) < 2) return $this->jsonError(t('users.name_min'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return $this->jsonError(t('users.email_invalid'));
        if (!$roleId && !$isAdmin) return $this->jsonError(t('users.need_role'));

        $db = db_connect();
        if ($db->table('users')->where('username', $username)->where('id !=', $id)->countAllResults())
            return $this->jsonError(t('users.dup_username'));
        $now = gmdate('Y-m-d H:i:s');
        $db->transBegin();
        try {
            if ($id) {
                $fields = ['username' => $username, 'display_name' => $name, 'email' => $email ?: null, 'is_admin' => $isAdmin, 'updated_at' => $now];
                if (mb_strlen($password) >= 8) $fields['password_hash'] = password_hash($password, PASSWORD_ARGON2ID);
                $db->table('users')->update($fields, ['id' => $id]);
                $db->table('user_roles')->where('user_id', $id)->delete();
                if ($roleId) $db->table('user_roles')->insert(['user_id' => $id, 'role_id' => $roleId]);
                $msg = t('users.updated');
            } else {
                if (mb_strlen($password) < 8) { $db->transRollback(); return $this->jsonError(t('users.pass_min')); }
                $db->table('users')->insert([
                    'username' => $username, 'display_name' => $name, 'email' => $email ?: null,
                    'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
                    'is_active' => 1, 'is_admin' => $isAdmin, 'must_change_password' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $uid = (int) $db->insertID();
                if ($roleId) $db->table('user_roles')->insert(['user_id' => $uid, 'role_id' => $roleId]);
                $msg = t('users.created');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->jsonError(t('imp.insert_failed') . $e->getMessage());
        }
        return $this->response->setJSON(['ok' => true, 'message' => $msg]);
    }

    /** فعال/غیرفعال‌سازی با محافظ آخرین مدیر سیستم */
    public function toggle(int $id)
    {
        $db = db_connect();
        $user = $db->table('users')->where('id', $id)->where('deleted_at', null)->get()->getFirstRow('array');
        if (!$user) return $this->jsonError(t('users.not_found'));
        if ($id === (int) session('user_id')) return $this->jsonError(t('users.self_toggle_err'));
        if ((int) $user['is_admin'] === 1 && (int) $user['is_active'] === 1) {
            $otherAdmins = $db->table('users')->where('is_admin', 1)->where('is_active', 1)->where('id !=', $id)->where('deleted_at', null)->countAllResults();
            if ($otherAdmins === 0) return $this->jsonError(t('users.last_admin_err'));
        }
        $new = (int) $user['is_active'] === 1 ? 0 : 1;
        $db->table('users')->update(['is_active' => $new, 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]);
        return $this->response->setJSON(['ok' => true, 'message' => $new ? t('users.activated') : t('users.deactivated')]);
    }

    private function jsonError(string $msg)
    {
        return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $msg]);
    }
}
