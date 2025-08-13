<?php
namespace App\Controllers;

class QuotationController
{
    public function index()
    {
        auth_required();
        return view('quotations/index');
    }

    public function show($id)
    {
        auth_required();
        return view('quotations/show', ['id' => $id]);
    }
}
