<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        // Forzar Accept: application/json en todas las rutas API
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}