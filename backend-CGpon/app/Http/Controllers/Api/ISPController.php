<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\GeneralHelper;
use App\Models\ISP;
use App\Models\Status;
use App\Http\Requests\StoreISPRequest;
use App\Http\Requests\UpdateISPRequest;
use App\Http\Requests\DeleteISPRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ISPController extends Controller
{
    /**
     * Listado de ISPs + statuses
     * Soporta paginación opcional (?page=..&per_page=..).
     */
    public function index(): JsonResponse
    {
        // Obtener el tipo de usuario actual
        $user_type = GeneralHelper::get_user_type_code();
    
        // Solo superadmin o main_provider pueden acceder
        if (!in_array($user_type, ['superadmin', 'main_provider'])) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para acceder a este recurso.'
            ], 403);
        }
    
        // Paginación opcional
        $perPage = request()->query('per_page');
    
        $query = ISP::leftJoin('statuses', 'isps.status_id', '=', 'statuses.id')
        ->select(
            'isps.id',
            'isps.name',
            'isps.description',
            'isps.created_at',
            'isps.updated_at',
            'statuses.id as status_id',
            'statuses.code as status_code',
            'statuses.name as status_name',
            DB::raw('(SELECT COUNT(*) FROM isp_olt WHERE isp_olt.isp_id = isps.id) as olts_count'),
            DB::raw('(SELECT COUNT(*) FROM users WHERE users.isp_id = isps.id) as users_count')
        )
        ->orderBy('isps.name');
    
        // Obtener resultados con o sin paginación
        $isps = $perPage ? $query->paginate((int)$perPage) : $query->get();
    
        // Traer todos los estados disponibles
        $statuses = Status::select('id', 'name', 'code')->get();
    
        // Respuesta JSON
        return response()->json([
            'success' => true,
            'data' => [
                'isps' => $isps,
                'statuses' => $statuses
            ]
        ]);
    }
    

    /**
     * Crear ISP
     */
    public function store(StoreISPRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $activeStatus = Status::where('code', 'active')->first();

            if (!$activeStatus) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Estado activo no encontrado en el sistema.'
                ], 404);
            }

            $isp = ISP::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status_id' => $activeStatus->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'ISP creado exitosamente.',
                'data' => $isp
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error creating ISP', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear ISP.'
                // opcional: en entorno de desarrollo se puede devolver 'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un ISP (usando route-model binding)
     */
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

    /**
     * Actualizar ISP
     */
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
        return $this->changeStatus($isp, 'active');
    }

    /**
     * Desactivar un ISP
     */
    public function deactivate(ISP $isp): JsonResponse
    {
        return $this->changeStatus($isp, 'inactive');
    }

    /**
     * Cambiar el estado de un ISP de manera segura (lock + transaction)
     */
    private function changeStatus(ISP $isp, string $targetStatusCode): JsonResponse
    {
        $user_type = GeneralHelper::get_user_type_code();

        if (!in_array($user_type, ['superadmin', 'main_provider'])) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para realizar esta acción.'
            ], 403);
        }

        try {
            $result = DB::transaction(function () use ($isp, $targetStatusCode) {
                $ispLocked = ISP::where('id', $isp->id)->lockForUpdate()->first();

                $targetStatus = Status::where('code', $targetStatusCode)->first();
                if (!$targetStatus) {
                    throw new \RuntimeException("Estado {$targetStatusCode} no configurado.");
                }

                $ispLocked->status_id = $targetStatus->id;
                $ispLocked->save();

                return $ispLocked;
            });

            return response()->json([
                'success' => true,
                'message' => "ISP {$targetStatusCode} correctamente.",
                'data' => [
                    'id' => $result->id,
                    'status_id' => $result->status_id
                ]
            ]);
        } catch (Throwable $e) {
            Log::error("Error changing ISP status to {$targetStatusCode}", [
                'id' => $isp->id,
                'error' => $e->getMessage()
            ]);
            $status = $e instanceof \RuntimeException ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e instanceof \RuntimeException ? $e->getMessage() : 'Error al cambiar estado.'
            ], $status);
        }
    }

    /**
     * Eliminar ISP
     */
    public function destroy(DeleteISPRequest $request, ISP $isp): JsonResponse
    {
        try {
            Log::info('Delete ISP', [
                'isp' => $isp->id,
                'user' => auth()->id(),
                'ip' => $request->ip()
            ]);

            DB::beginTransaction();

            // usar exists() es más eficiente que count()
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
