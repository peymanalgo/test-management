<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCoreSchema extends Migration
{
    public function up()
    {
        $this->forge->addField(['id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],'username'=>['type'=>'VARCHAR','constraint'=>80],'display_name'=>['type'=>'VARCHAR','constraint'=>150],'email'=>['type'=>'VARCHAR','constraint'=>190,'null'=>true],'password_hash'=>['type'=>'VARCHAR','constraint'=>255],'is_active'=>['type'=>'TINYINT','constraint'=>1,'default'=>1],'is_admin'=>['type'=>'TINYINT','constraint'=>1,'default'=>0],'must_change_password'=>['type'=>'TINYINT','constraint'=>1,'default'=>1],'last_login_at'=>['type'=>'DATETIME','null'=>true],'created_at'=>['type'=>'DATETIME','null'=>true],'updated_at'=>['type'=>'DATETIME','null'=>true],'deleted_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->forge->addKey('id', true); $this->forge->addUniqueKey('username'); $this->forge->addUniqueKey('email'); $this->forge->createTable('users', true, ['ENGINE'=>'InnoDB','DEFAULT CHARACTER SET'=>'utf8mb4']);
        $this->forge->addField(['id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],'code'=>['type'=>'VARCHAR','constraint'=>30],'name'=>['type'=>'VARCHAR','constraint'=>80],'created_at'=>['type'=>'DATETIME','null'=>true]]); $this->forge->addKey('id',true); $this->forge->addUniqueKey('code'); $this->forge->createTable('roles',true,['ENGINE'=>'InnoDB']);
        $this->forge->addField(['user_id'=>['type'=>'BIGINT','unsigned'=>true],'role_id'=>['type'=>'BIGINT','unsigned'=>true]]); $this->forge->addKey(['user_id','role_id'],true); $this->forge->addForeignKey('user_id','users','id','RESTRICT','CASCADE'); $this->forge->addForeignKey('role_id','roles','id','RESTRICT','CASCADE'); $this->forge->createTable('user_roles',true,['ENGINE'=>'InnoDB']);
        $this->forge->addField(['id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],'actor_user_id'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],'entity_type'=>['type'=>'VARCHAR','constraint'=>60],'entity_id'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],'action'=>['type'=>'VARCHAR','constraint'=>80],'ip_address'=>['type'=>'VARCHAR','constraint'=>45,'null'=>true],'created_at'=>['type'=>'DATETIME']]); $this->forge->addKey('id',true); $this->forge->createTable('audit_logs',true,['ENGINE'=>'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('audit_logs', true); $this->forge->dropTable('user_roles', true); $this->forge->dropTable('roles', true); $this->forge->dropTable('users', true);
    }
}
