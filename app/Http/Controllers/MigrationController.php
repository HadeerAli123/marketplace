<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class MigrationController extends Controller
{
    /**
     * Run the migrations.
     */
    public function runMigrations(Request $request)
    {
        try {
            // Run migrations
            Artisan::call('migrate');

            return response()->json([
                'status' => true,
                'message' => 'Migrations ran successfully.',
                'output' => Artisan::output(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error running migrations: ' . $e->getMessage(),
            ], 500);
        }
    }
}
