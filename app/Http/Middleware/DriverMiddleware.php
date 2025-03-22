<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DriverMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->role === 'driver') {
            return $next($request);
        }
        return response()->json(['message' => 'Access Denied. Drivers only.'], 403);
    }
}
