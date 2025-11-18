<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    // Login
    public function login(Request $request)
    {
        $credentials = $request->only('username', 'password');

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        $user = Auth::guard('api')->user();

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $this->formatUserResponse($user)
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        Auth::guard('api')->logout();
        return response()->json(['success' => true, 'message' => 'Sesión cerrada']);
    }

    // Obtener info del usuario autenticado
    public function me()
    {
        $user = Auth::guard('api')->user();

        return response()->json([
            'user' => $user ? $this->formatUserResponse($user) : null
        ]);
    }

    // Refresh token
    public function refresh()
    {
        try {
            // Intentar refrescar el token (JWT permite refrescar tokens expirados dentro del refresh_ttl)
            /** @var \Tymon\JWTAuth\JWTGuard $guard */
            $guard = Auth::guard('api');
            $newToken = $guard->refresh();
            
            return response()->json([
                'success' => true,
                'token' => $newToken
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo refrescar el token. Por favor, inicie sesión nuevamente.'
            ], 401);
        }
    }

    private function formatUserResponse(User $user): array
    {
        $user->loadMissing(['userType', 'isp']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'user_type_id' => $user->user_type_id,
            'user_type_code' => $user->userType->code ?? null,
            'user_type_name' => $user->userType->name ?? null,
            'user_type' => $user->userType->name ?? null,
            'status' => $user->status,
            'isp_id' => $user->isp_id,
            'isp_name' => $user->isp->name ?? null,
            'isp' => $user->isp->name ?? null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
