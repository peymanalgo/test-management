<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class WorkbenchController extends BaseController
{
    public function index()
    {
        if(!session('user_id'))return redirect()->to('/login'); return view('modules/workbench',['items'=>db_connect()->query('SELECT rc.*,tc.code,tc.title,tr.name run_name FROM test_run_cases rc JOIN test_cases tc ON tc.id=rc.test_case_id JOIN test_runs tr ON tr.id=rc.test_run_id ORDER BY rc.id DESC')->getResultArray()]);
    }
}
