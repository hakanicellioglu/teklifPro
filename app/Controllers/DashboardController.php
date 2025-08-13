<?php
namespace App\Controllers;

class DashboardController
{
    public function index()
    {
        auth_required();
        return view('dashboard/index');
    }
}
