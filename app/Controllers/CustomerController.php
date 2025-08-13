<?php
namespace App\Controllers;

class CustomerController
{
    public function index()
    {
        auth_required();
        return view('customers/index');
    }
}
