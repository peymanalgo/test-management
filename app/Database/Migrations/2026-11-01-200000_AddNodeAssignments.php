<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNodeAssignments extends Migration
{
    public function up()
    {
        // انتساب سه مسئول (آزمونگر/برنامه‌نویس/مدیر پروژه) به هر گره از درخت ماژول‌های پروژه
        $this->db->query('CREATE TABLE IF NOT EXISTS node_assignments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            node_id BIGINT UNSIGNED NOT NULL UNIQUE,
            tester_id BIGINT UNSIGNED NULL,
            developer_id BIGINT UNSIGNED NULL,
            manager_id BIGINT UNSIGNED NULL,
            assigned_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY na_tester (tester_id),
            KEY na_developer (developer_id),
            KEY na_manager (manager_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public function down()
    {
        $this->forge->dropTable('node_assignments', true);
    }
}
