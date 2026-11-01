<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class DefectsController extends BaseController
{
    public function index()
    {
        if(!session('user_id'))return redirect()->to('/login'); return view('modules/defects',['defects'=>db_connect()->query('SELECT d.*,p.name project_name FROM defects d JOIN projects p ON p.id=d.project_id ORDER BY d.id DESC')->getResultArray()]);
    }
}
