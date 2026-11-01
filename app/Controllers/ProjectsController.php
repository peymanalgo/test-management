<?php

namespace App\Controllers;

use App\Models\ProjectModel;

class ProjectsController extends BaseController
{
    public function index()
    {
        if (!session('user_id')) return redirect()->to('/login');
        $model = new ProjectModel();
        if (strtolower($this->request->getMethod()) === 'post') {
            if (!session('is_admin')) return $this->response->setStatusCode(403);
            $data = ['code'=>strtoupper(trim((string)$this->request->getPost('code'))), 'name'=>trim((string)$this->request->getPost('name')), 'description'=>trim((string)$this->request->getPost('description')), 'target_url'=>trim((string)$this->request->getPost('target_url')), 'status'=>'ACTIVE', 'created_by'=>session('user_id')];
            if (!$this->validateData($data, ['code'=>'required|alpha_numeric_punct|max_length[50]|is_unique[projects.code]', 'name'=>'required|max_length[180]', 'target_url'=>'permit_empty|valid_url_strict'])) return view('projects/index', ['projects'=>$model->orderBy('id','DESC')->findAll(), 'validation'=>$this->validator]);
            $model->insert($data); return redirect()->to('/projects')->with('message', 'پروژه ساخته شد.');
        }
        return view('projects/index', ['projects'=>$model->orderBy('id','DESC')->findAll()]);
    }
}
