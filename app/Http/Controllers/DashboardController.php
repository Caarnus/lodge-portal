<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard)
    {
        return Inertia::render('Dashboard', $dashboard->read($request->user()));
    }
}
