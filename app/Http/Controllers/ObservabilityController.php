<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ObservabilityController extends Controller
{
    public function run(Request $request)
    {
        if (! env('OBSERVABILITY_DEPLOY_TOKEN') || $request->header('X-Obs-Token') !== env('OBSERVABILITY_DEPLOY_TOKEN')) {
            return response()->json(['status' => 'Not Found'], 404);
        }

        $exitCode = Artisan::call('observability:deploy');

        if ($exitCode === 0) {
            return response()->json(['status' => 'Success', 'output' => Artisan::output()]);
        }

        return response()->json(['status' => 'Failed', 'output' => Artisan::output()], 500);
    }
}
