<?php

namespace App\Http\Controllers;

use App\Models\CheckoutAcceptance;
use Illuminate\Http\Request;

class UnauthenticatedAcceptanceController extends Controller
{
    public function show(Request $request, $acceptanceUuid)
    {
        $acceptance = CheckoutAcceptance::where(['uuid' => $acceptanceUuid])->firstOrFail();

        if (!$acceptance->isPending() || !$acceptance->allowsUnauthenticatedAcceptance()) {
            abort(404);
        }

        // @todo:
        // return view();

        return $acceptance->checkoutable->present()->name();
    }
}
