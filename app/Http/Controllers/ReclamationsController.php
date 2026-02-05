<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ReclamationsController extends Controller
{
    public function index(): View
    {
        return view('reclamations.index');
    }
}
