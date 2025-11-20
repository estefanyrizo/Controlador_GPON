<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Mostrar información del perfil del usuario.
     */
    public function edit(Request $request): JsonResponse
    {
        $user = $request->user()->load(['userType']); // Carga relaciones

        return response()->json([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status, // devuelve el nombre del estado
                'type' => $user->userType?->name,     // devuelve el nombre del tipo
            ],
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
        ]);
    }

    /**
     * Actualizar información del perfil del usuario.
     */
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $user->load(['userType']); // recargar relaciones

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'type' => $user->userType?->name,
            ],
        ]);
    }

    /**
     * Eliminar la cuenta del usuario.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Cuenta eliminada correctamente',
        ], 200);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required','string','min:6','confirmed'],
        ]);

        $user = $request->user();
        $user->password = $request->password;
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

}
