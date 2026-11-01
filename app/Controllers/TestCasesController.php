<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class TestCasesController extends BaseController
{
    public function index()
    {
        if (!session('user_id')) return redirect()->to('/login');
        $db=db_connect();
        if (strtolower($this->request->getMethod())==='post') {
            $project=(int)$this->request->getPost('project_id'); $title=trim((string)$this->request->getPost('title')); $code=strtoupper(trim((string)$this->request->getPost('code')));
            if (!$project||!$title||!$code) return redirect()->back()->with('error','کد، عنوان و پروژه الزامی هستند.');
            $node=$db->table('project_nodes')->where('project_id',$project)->get()->getFirstRow('array'); if(!$node) return redirect()->back()->with('error','ابتدا یک ماژول برای پروژه بسازید.');
            $db->table('test_cases')->insert(['project_id'=>$project,'node_id'=>$node['id'],'code'=>$code,'title'=>$title,'expected_result'=>(string)$this->request->getPost('expected_result'),'priority'=>(string)$this->request->getPost('priority'),'test_type'=>'FUNCTIONAL','status'=>'DRAFT','created_by'=>session('user_id'),'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]); return redirect()->to('/test-cases')->with('message','سناریو به‌صورت پیش‌نویس ثبت شد.');
        }
        return view('modules/test_cases',['cases'=>$db->query('SELECT t.*,p.name project_name FROM test_cases t JOIN projects p ON p.id=t.project_id ORDER BY t.id DESC')->getResultArray(),'projects'=>$db->table('projects')->where('status','ACTIVE')->get()->getResultArray()]);
    }
}
