<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, ...$roles): JsonResponse|Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->loadMissing('userType');
        $userRole = $user->userType->code ?? null;

        if (empty($roles)) {
            return $next($request);
        }

        $normalizedRoles = array_map(fn ($role) => strtolower($role), $roles);
        $roleAllowed = $userRole && in_array($userRole, $normalizedRoles, true);

        if (!$roleAllowed) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para ejecutar esta acción.'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($userRole === 'isp_representative' && empty($user->isp_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Tu usuario no tiene un ISP asignado. Contacta al administrador.'
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}




