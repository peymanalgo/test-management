<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class TestRunsController extends BaseController
{
    public function index()
    {
        if (!session('user_id')) return redirect()->to('/login'); $db=db_connect();
        if(strtolower($this->request->getMethod())==='post'){ $project=(int)$this->request->getPost('project_id'); $name=trim((string)$this->request->getPost('name')); if(!$project||!$name)return redirect()->back()->with('error','پروژه و نام دوره الزامی‌اند.'); $db->table('test_runs')->insert(['project_id'=>$project,'name'=>$name,'status'=>'OPEN','created_by'=>session('user_id'),'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]); return redirect()->to('/test-runs')->with('message','دورهٔ تست ایجاد شد.');} return view('modules/test_runs',['runs'=>$db->query('SELECT r.*,p.name project_name FROM test_runs r JOIN projects p ON p.id=r.project_id ORDER BY r.id DESC')->getResultArray(),'projects'=>$db->table('projects')->where('status','ACTIVE')->get()->getResultArray()]);
    }
}
