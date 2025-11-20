<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\GeneralHelper;
use App\Models\ISP;
use App\Http\Requests\StoreISPRequest;
use App\Http\Requests\UpdateISPRequest;
use App\Http\Requests\DeleteISPRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ISPController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:superadmin,main_provider');
    }

    public function index(): JsonResponse
    {
        $user_type = GeneralHelper::get_user_type_code();

        if (!in_array($user_type, ['superadmin', 'main_provider'])) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para acceder a este recurso.'
            ], 403);
        }

        $perPage = request()->query('per_page');

        $query = ISP::select(
            'id',
            'name',
            'description',
            'created_at',
            'updated_at',
            DB::raw("CASE WHEN status THEN 'Activo' ELSE 'Inactivo' END as status"),
            DB::raw('(SELECT COUNT(*) FROM isp_olt WHERE isp_olt.isp_id = isps.id) as olts_count'),
            DB::raw('(SELECT COUNT(*) FROM users WHERE users.isp_id = isps.id) as users_count')
        )->orderBy('name');

        $isps = $perPage ? $query->paginate((int)$perPage) : $query->get();

        return response()->json([
            'success' => true,
            'data' => ['isps' => $isps]
        ]);
    }

    public function store(StoreISPRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $isp = ISP::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => true, // activo por defecto
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'ISP creado exitosamente.',
                'data' => $isp
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error creating ISP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear ISP.'
            ], 500);
        }
    }

    public function show(ISP $isp): JsonResponse
    {
        $isp = ISP::withCount(['olts', 'users'])->find($isp->id);

        if (!$isp) {
            return response()->json([
                'success' => false,
                'message' => 'ISP no encontrado.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $isp
        ]);
    }

    public function update(UpdateISPRequest $request, ISP $isp): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $isp->fill($request->only(['name', 'description']));
            $isp->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'ISP actualizado correctamente.',
                'data' => $isp
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error updating ISP', ['id' => $isp->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar ISP.'
            ], 500);
        }
    }

    public function activate(ISP $isp): JsonResponse
    {
        return $this->changeStatus($isp, true);
    }

    public function deactivate(ISP $isp): JsonResponse
    {
        return $this->changeStatus($isp, false);
    }

    private function changeStatus(ISP $isp, bool $status): JsonResponse
    {
        $user_type = GeneralHelper::get_user_type_code();

        if (!in_array($user_type, ['superadmin', 'main_provider'])) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para realizar esta acción.'
            ], 403);
        }

        if ($isp->status === $status) {
            return response()->json([
                'success' => false,
                'message' => $status ? 'El ISP ya está activado.' : 'El ISP ya está desactivado.'
            ]);
        }

        try {
            $isp->status = $status;
            $isp->save();

            return response()->json([
                'success' => true,
                'message' => $status ? 'ISP activado correctamente.' : 'ISP desactivado correctamente.',
                'data' => [
                    'id' => $isp->id,
                    'status' => $isp->status
                ]
            ]);
        } catch (Throwable $e) {
            Log::error("Error cambiando status de ISP {$isp->id}", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado.'
            ], 500);
        }
    }

    public function destroy(DeleteISPRequest $request, ISP $isp): JsonResponse
    {
        try {
            Log::info('Delete ISP', [
                'isp' => $isp->id,
                'user' => auth()->id(),
                'ip' => $request->ip()
            ]);

            DB::beginTransaction();

            if ($isp->olts()->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar, tiene OLTs asociados.'
                ], 409);
            }

            if ($isp->users()->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar, tiene usuarios asociados.'
                ], 409);
            }

            $isp->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'ISP eliminado correctamente.'
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error delete ISP', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar ISP.'
            ], 500);
        }
    }
}
