<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashbordController extends Controller
{
    public function getDrivers(Request $request)
    {
        $drivers = User::where('role', 'driver')->get();

        return response()->json([
            'message' => 'Drivers retrieved successfully',
            'drivers' => $drivers
        ], 200);
    }
}
