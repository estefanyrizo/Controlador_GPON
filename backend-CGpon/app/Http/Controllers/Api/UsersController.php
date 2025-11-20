<?php

namespace App\Http\Controllers\Api;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\ISP;
use App\Models\Status;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:superadmin,main_provider');
    }
    /**
     * List all users with related data for API.
     */
    public function index()
    {
        $user_type = GeneralHelper::get_user_type_code();

        if ($user_type == 'superadmin') {
            $user_types = UserType::select('id', 'name as label', 'code')->get();
        } elseif ($user_type == 'main_provider') {
            $user_types = UserType::whereIn('code', ['main_provider', 'isp_representative'])
                                  ->select('id', 'name as label', 'code')
                                  ->get();
        } else {
            $user_types = null;
        }

        $isps = ($user_type == 'superadmin' || $user_type == 'main_provider')
            ? ISP::active()->select('id', 'name as label')->get()
            : null;


            $users = User::leftJoin('isps', 'users.isp_id', '=', 'isps.id')
            ->join('user_types', 'users.user_type_id', '=', 'user_types.id')
            ->select(
                'users.id',
                'users.name',
                'users.username',
                'users.email',
                'users.isp_id',
                'users.user_type_id',
                'users.status as status', // booleano
                'isps.name as isp_name',
                DB::raw("CASE WHEN users.status THEN 'Activo' ELSE 'Inactivo' END as status"),
                'user_types.name as user_type'
            )
            ->get();
        

        return response()->json([
            'user_types' => $user_types,
            'isps' => $isps,
            'users' => $users
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $user = User::create($validated);

            DB::commit();
            event(new Registered($user));

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Check if user type is changing from isp_representative to another type
        if (isset($validated['user_type_id']) && $validated['user_type_id'] != $user->user_type_id) {
            $currentUserType = UserType::find($user->user_type_id);
            $newUserType = UserType::find($validated['user_type_id']);

            if ($currentUserType && $currentUserType->user_type_code === 'isp_representative' &&
                $newUserType && $newUserType->user_type_code !== 'isp_representative') {
                $validated['isp_id'] = null;
            }
        }

        try {
            DB::beginTransaction();
            $user->update($validated);
            DB::commit();

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a single user.
     */
    public function show(User $user)
    {
        return response()->json($user);
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user)
    {
        try {
            $user->delete();
            return response()->json(['message' => 'Usuario eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function activate(User $user)
    {
        if ($user->status) {
            return response()->json([
                'message' => 'El usuario ya está activado.',
                'user' => $user
            ]);
        }
    
        try {
            $user->status = true;
            $user->save();
    
            return response()->json([
                'message' => 'Usuario activado correctamente.',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al activar usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function deactivate(User $user)
    {
        if (!$user->status) {
            return response()->json([
                'message' => 'El usuario ya está desactivado.',
                'user' => $user
            ]);
        }
    
        try {
            $user->status = false;
            $user->save();
    
            return response()->json([
                'message' => 'Usuario desactivado correctamente.',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al desactivar usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

    public function updatePassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|min:6'
        ], [
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.'
        ]);

        try {
            DB::beginTransaction();
            $user->password = $request->password;
            $user->save();
            DB::commit();

            return response()->json([
                'message' => 'Contraseña actualizada correctamente.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la contraseña.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
}
