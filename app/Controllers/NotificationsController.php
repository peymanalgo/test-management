<?php

namespace App\Controllers;

/** اعلان‌های درون‌برنامه‌ای (مشترک ادمین و کاربران) */
class NotificationsController extends BaseController
{
    public function index()
    {
        $rows = db_connect()->table('notifications')
            ->where('user_id', session('user_id'))
            ->orderBy('id', 'DESC')->limit(100)->get()->getResultArray();
        return view('notifications/index', ['rows' => $rows]);
    }

    public function latest()
    {
        $db = db_connect();
        $rows = $db->table('notifications')->where('user_id', session('user_id'))->orderBy('id', 'DESC')->limit(8)->get()->getResultArray();
        $unread = $db->table('notifications')->where('user_id', session('user_id'))->where('read_at', null)->countAllResults();
        return $this->response->setJSON(['ok' => true, 'rows' => $rows, 'unread' => $unread]);
    }

    public function read(int $id)
    {
        db_connect()->table('notifications')->where('id', $id)->where('user_id', session('user_id'))->update(['read_at' => gmdate('Y-m-d H:i:s')]);
        return $this->response->setJSON(['ok' => true]);
    }

    public function readAll()
    {
        db_connect()->table('notifications')->where('user_id', session('user_id'))->where('read_at', null)->update(['read_at' => gmdate('Y-m-d H:i:s')]);
        return $this->response->setJSON(['ok' => true]);
    }
}
