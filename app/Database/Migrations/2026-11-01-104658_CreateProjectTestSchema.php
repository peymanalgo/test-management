<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProjectTestSchema extends Migration
{
    public function up()
    {
        $tables = [
            'projects'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','code VARCHAR(50) NOT NULL UNIQUE','name VARCHAR(180) NOT NULL','description TEXT NULL','target_url VARCHAR(2048) NULL','status VARCHAR(20) NOT NULL DEFAULT \'ACTIVE\'','created_by BIGINT UNSIGNED NOT NULL','created_at DATETIME NULL','updated_at DATETIME NULL','archived_at DATETIME NULL'],
            'project_members'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','project_id BIGINT UNSIGNED NOT NULL','user_id BIGINT UNSIGNED NOT NULL','role_code VARCHAR(20) NOT NULL','created_at DATETIME NULL','UNIQUE KEY project_member (project_id,user_id,role_code)'],
            'project_nodes'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','project_id BIGINT UNSIGNED NOT NULL','parent_id BIGINT UNSIGNED NULL','name VARCHAR(180) NOT NULL','description TEXT NULL','sort_order INT NOT NULL DEFAULT 0','is_active TINYINT(1) NOT NULL DEFAULT 1','created_at DATETIME NULL','updated_at DATETIME NULL'],
            'project_versions'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','project_id BIGINT UNSIGNED NOT NULL','version_label VARCHAR(80) NOT NULL','release_notes TEXT NULL','target_url VARCHAR(2048) NULL','released_at DATETIME NULL','created_at DATETIME NULL','UNIQUE KEY project_version (project_id,version_label)'],
            'test_cases'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','project_id BIGINT UNSIGNED NOT NULL','node_id BIGINT UNSIGNED NOT NULL','code VARCHAR(60) NOT NULL','title VARCHAR(255) NOT NULL','objective TEXT NULL','preconditions TEXT NULL','test_data TEXT NULL','expected_result TEXT NOT NULL','priority VARCHAR(20) NOT NULL','test_type VARCHAR(20) NOT NULL','status VARCHAR(20) NOT NULL DEFAULT \'DRAFT\'','current_revision INT NOT NULL DEFAULT 1','created_by BIGINT UNSIGNED NOT NULL','updated_by BIGINT UNSIGNED NULL','created_at DATETIME NULL','updated_at DATETIME NULL','archived_at DATETIME NULL','UNIQUE KEY test_case_code (project_id,code)'],
            'test_case_steps'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','test_case_id BIGINT UNSIGNED NOT NULL','step_order INT NOT NULL','instruction TEXT NOT NULL','expected_result TEXT NULL','UNIQUE KEY case_step (test_case_id,step_order)'],
            'test_case_revisions'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','test_case_id BIGINT UNSIGNED NOT NULL','revision_no INT NOT NULL','snapshot_json LONGTEXT NOT NULL','reason VARCHAR(500) NULL','created_by BIGINT UNSIGNED NOT NULL','created_at DATETIME NOT NULL','UNIQUE KEY case_revision (test_case_id,revision_no)'],
            'test_assignments'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','test_case_id BIGINT UNSIGNED NOT NULL UNIQUE','tester_id BIGINT UNSIGNED NOT NULL','developer_id BIGINT UNSIGNED NOT NULL','manager_id BIGINT UNSIGNED NOT NULL','assigned_by BIGINT UNSIGNED NOT NULL','created_at DATETIME NULL','updated_at DATETIME NULL'],
            'test_runs'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','project_id BIGINT UNSIGNED NOT NULL','project_version_id BIGINT UNSIGNED NULL','name VARCHAR(180) NOT NULL','description TEXT NULL','status VARCHAR(20) NOT NULL DEFAULT \'OPEN\'','created_by BIGINT UNSIGNED NOT NULL','created_at DATETIME NULL','updated_at DATETIME NULL'],
            'test_run_cases'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','test_run_id BIGINT UNSIGNED NOT NULL','test_case_id BIGINT UNSIGNED NOT NULL','test_case_revision_id BIGINT UNSIGNED NOT NULL','workflow_status VARCHAR(30) NOT NULL DEFAULT \'READY\'','lock_version INT NOT NULL DEFAULT 1','added_at DATETIME NOT NULL','UNIQUE KEY run_case (test_run_id,test_case_id)','KEY workbench (workflow_status)'],
            'test_executions'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','test_run_case_id BIGINT UNSIGNED NOT NULL','test_case_revision_id BIGINT UNSIGNED NOT NULL','execution_no INT NOT NULL','tester_id BIGINT UNSIGNED NOT NULL','result VARCHAR(20) NOT NULL','actual_result TEXT NULL','notes TEXT NULL','executed_at DATETIME NOT NULL','created_at DATETIME NOT NULL','UNIQUE KEY execution_no (test_run_case_id,execution_no)'],
            'defects'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','project_id BIGINT UNSIGNED NOT NULL','test_run_case_id BIGINT UNSIGNED NOT NULL','execution_id BIGINT UNSIGNED NOT NULL','code VARCHAR(60) NOT NULL','title VARCHAR(255) NOT NULL','description TEXT NOT NULL','severity VARCHAR(20) NOT NULL','status VARCHAR(30) NOT NULL DEFAULT \'OPEN\'','developer_id BIGINT UNSIGNED NOT NULL','reported_by BIGINT UNSIGNED NOT NULL','fix_notes TEXT NULL','created_at DATETIME NULL','updated_at DATETIME NULL'],
            'workflow_history'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','test_run_case_id BIGINT UNSIGNED NOT NULL','from_status VARCHAR(30) NULL','to_status VARCHAR(30) NOT NULL','actor_user_id BIGINT UNSIGNED NOT NULL','reason TEXT NULL','created_at DATETIME NOT NULL'],
            'notifications'=>['id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','user_id BIGINT UNSIGNED NOT NULL','project_id BIGINT UNSIGNED NULL','type VARCHAR(40) NOT NULL','title VARCHAR(255) NOT NULL','message TEXT NOT NULL','target_url VARCHAR(2048) NULL','read_at DATETIME NULL','created_at DATETIME NOT NULL']
        ];
        foreach ($tables as $name => $fields) $this->db->query('CREATE TABLE '.$name.' ('.implode(',', $fields).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public function down()
    {
        foreach (['notifications','workflow_history','defects','test_executions','test_run_cases','test_runs','test_assignments','test_case_revisions','test_case_steps','test_cases','project_versions','project_nodes','project_members','projects'] as $table) $this->forge->dropTable($table, true);
    }
}
