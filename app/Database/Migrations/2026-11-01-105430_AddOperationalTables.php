<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOperationalTables extends Migration
{
    public function up()
    {
        $sql=['CREATE TABLE defect_comments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,defect_id BIGINT UNSIGNED NOT NULL,author_id BIGINT UNSIGNED NOT NULL,body TEXT NOT NULL,created_at DATETIME NOT NULL,KEY(defect_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4','CREATE TABLE attachments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,project_id BIGINT UNSIGNED NOT NULL,owner_type VARCHAR(20) NOT NULL,owner_id BIGINT UNSIGNED NOT NULL,original_name VARCHAR(255) NOT NULL,stored_name VARCHAR(255) NOT NULL,mime_type VARCHAR(100) NOT NULL,size_bytes INT UNSIGNED NOT NULL,uploaded_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,KEY(project_id,owner_type,owner_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4','CREATE TABLE import_jobs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,project_id BIGINT UNSIGNED NOT NULL,file_name VARCHAR(255) NOT NULL,status VARCHAR(20) NOT NULL,success_count INT NOT NULL DEFAULT 0,error_count INT NOT NULL DEFAULT 0,error_report LONGTEXT NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4','CREATE TABLE settings (`key` VARCHAR(100) PRIMARY KEY,`value` TEXT NULL,updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4']; foreach($sql as $q)$this->db->query($q);
    }

    public function down()
    {
        foreach(['settings','import_jobs','attachments','defect_comments'] as $t)$this->forge->dropTable($t,true);
    }
}
