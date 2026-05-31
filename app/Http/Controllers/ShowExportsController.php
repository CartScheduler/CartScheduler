<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ShowExportsController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Exports/Show');
    }
}
