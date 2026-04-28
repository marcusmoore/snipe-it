<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomAccessoryReportController extends Controller
{
    public function show(Request $request)
    {
        return view('reports.custom.accessory');
    }

    public function run() {}
}
