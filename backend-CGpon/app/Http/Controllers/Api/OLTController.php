<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\GeneralHelper;
use App\Models\ISP;
use App\Models\OLT;
use App\Http\Requests\StoreOLTRequest;
use App\Http\Requests\UpdateOLTRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OLTController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:superadmin,main_provider');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user_type = GeneralHelper::get_user_type_code();

        // Get ISPs
        if ($user_type == 'superadmin' || $user_type == 'main_provider') {
            $isps = ISP::active()->select('id', 'name as label')->get();
        } elseif ($user_type == 'isp_representative') {
            $isps = ISP::where('id', $request->user()->isp_id)
                ->active()
                ->select('id', 'name as label')
                ->get();
        } else {
            $isps = null;
        }

        // OLTs query
        $olts = OLT::with(['isps', 'creator']);

        if ($user_type == 'isp_representative') {
            $ispId = $request->user()->isp_id;
            $olts->whereHas('isps', fn($q) => $q->where('isps.id', $ispId));
        }

        // Filters by ISP
        $ispFilters = $selectedIspFilters = [];
        if ($request->has('isp_filters')) {
            $requestFilters = $request->input('isp_filters');
            $ispFilters = is_array($requestFilters) ? array_filter($requestFilters) : [$requestFilters];
            $selectedIspFilters = $ispFilters;
        }

        if (!empty($ispFilters)) {
            $olts->whereHas('isps', fn($q) => $q->whereIn('isps.id', $ispFilters));
        }

        // Filters by status
        $statusFilter = $request->input('status_filter', 'all');
        $selectedStatusFilter = $statusFilter;
        if ($statusFilter !== 'all') {
            $olts->where('status', $statusFilter === 'active');
        }

        $olts = $olts
            ->with(['creator', 'isps'])
            ->withCount('customers')
            ->get()
            ->map(function ($olt) {
                return [
                    'id' => $olt->id,
                    'name' => $olt->name,
                    'ip_olt' => $olt->ip_olt,
                    'description' => $olt->description,
                    'port' => $olt->port,
                    'username' => $olt->username,
                    'password' => $olt->password,
                    'must_login' => $olt->must_login,
                    'created_by' => $olt->created_by,
                    'created_by_user' => $olt->creator?->name,
                    'status_name' => $olt->status ? 'Activo' : 'Inactivo',
                    'customers_count' => $olt->customers_count,
                    'isp_name' => $olt->isps->pluck('name')->implode(', '),
                    'isp_id' => $olt->isps->first()?->id,
                ];
            });

        return response()->json([
            'olts' => $olts,
            'isps' => $isps,
            'selectedIspFilters' => $selectedIspFilters,
            'selectedStatusFilter' => $selectedStatusFilter,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOLTRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $olt = OLT::create([
                'name' => $validated['name'],
                'ip_olt' => $validated['ip_olt'],
                'description' => $validated['description'] ?? null,
                'port' => $validated['port'],
                'username' => $validated['username'],
                'password' => $validated['password'],
                'must_login' => $validated['must_login'],
                'status' => true, // activo por defecto
                'created_by' => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'OLT creado exitosamente',
                'olt' => $olt
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al crear OLT',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(OLT $olt): JsonResponse
    {
        return response()->json($olt->load(['creator', 'isps']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOLTRequest $request, OLT $olt): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            if (GeneralHelper::get_user_type_code() == 'isp_representative') {
                $userIspId = $request->user()->isp_id;
                $hasAccess = $olt->isps()->where('isps.id', $userIspId)->exists();
                if (!$hasAccess) return response()->json(['error' => 'No tiene permiso para actualizar este OLT.'], 403);
            }

            $olt->update([
                'name' => $validated['name'],
                'ip_olt' => $validated['ip_olt'],
                'description' => $validated['description'] ?? null,
                'port' => $validated['port'],
                'username' => $validated['username'],
                'password' => $validated['password'],
                'must_login' => $validated['must_login'],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'OLT actualizado exitosamente',
                'olt' => $olt
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al actualizar OLT',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OLT $olt): JsonResponse
    {
        try {
            DB::beginTransaction();

            if ($olt->customers()->count() > 0) {
                return response()->json(['error' => 'No se puede eliminar este OLT porque tiene clientes asociados.'], 422);
            }

            if (GeneralHelper::get_user_type_code() == 'isp_representative') {
                $userIspId = request()->user()->isp_id;
                $hasAccess = $olt->isps()->where('isps.id', $userIspId)->exists();
                if (!$hasAccess) return response()->json(['error' => 'No tiene permiso para eliminar este OLT.'], 403);
            }

            $olt->isps()->detach();
            $olt->delete();

            DB::commit();

            return response()->json(['message' => 'OLT eliminado exitosamente']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al eliminar OLT',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the relationships between an OLT and its ISPs.
     */
    public function updateRelations(OLT $olt, Request $request): JsonResponse
    {
        $request->validate([
            'relationships' => 'required|array',
            'relationships.*.isp_id' => 'required|exists:isps,id',
            'relationships.*.relation_name' => 'nullable|string|max:255',
            'relationships.*.relation_notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            if (GeneralHelper::get_user_type_code() == 'isp_representative') {
                $userIspId = $request->user()->isp_id;
                $hasAccess = $olt->isps()->where('isps.id', $userIspId)->exists();
                if (!$hasAccess) return response()->json(['error' => 'No tiene permiso para actualizar este OLT.'], 403);
            }

            // Detach todas las relaciones
            $olt->isps()->detach();

            foreach ($request->relationships as $relationship) {
                $isp = ISP::find($relationship['isp_id']);
                $olt->isps()->attach($relationship['isp_id'], [
                    'relation_name' => $relationship['relation_name'] ?? "Relación {$olt->name} - " . ($isp?->name ?? ''),
                    'relation_notes' => $relationship['relation_notes'] ?? "",
                    'status' => true, // activo por defecto en pivot
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Relaciones actualizadas exitosamente']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al actualizar relaciones',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the detailed ISP relationships for an OLT
     */
    public function getRelations(OLT $olt): JsonResponse
    {
        try {
            if (GeneralHelper::get_user_type_code() == 'isp_representative') {
                $userIspId = request()->user()->isp_id;
                $hasAccess = $olt->isps()->where('isps.id', $userIspId)->exists();
                if (!$hasAccess) return response()->json(['error' => 'No tiene permiso para ver este OLT.'], 403);
            }

            $relations = $olt->isps()->get()->map(fn($isp) => [
                'isp_id' => $isp->id,
                'isp_name' => $isp->name,
                'relation_name' => $isp->pivot->relation_name,
                'relation_notes' => $isp->pivot->relation_notes,
                'status' => $isp->pivot->status ? 'Activo' : 'Inactivo',
            ]);

            $creator = $olt->created_by ? \App\Models\User::find($olt->created_by)?->name : null;

            return response()->json([
                'relations' => $relations,
                'created_by_user' => $creator
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener relaciones',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function activate(OLT $olt): JsonResponse
    {
        return $this->changeStatus($olt, true);
    }
    
    public function deactivate(OLT $olt): JsonResponse
    {
        return $this->changeStatus($olt, false);
    }
    
    private function changeStatus(OLT $olt, bool $status): JsonResponse
    {
        $user_type = GeneralHelper::get_user_type_code();
    
        if (!in_array($user_type, ['superadmin', 'main_provider'])) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para realizar esta acción.'
            ], 403);
        }
    
        if ($olt->status === $status) {
            return response()->json([
                'success' => false,
                'message' => $status ? 'La OLT ya está activado.' : 'La OLT ya está desactivada.'
            ]);
        }
    
        try {
            $olt->status = $status;
            $olt->save();
    
            return response()->json([
                'success' => true,
                'message' => $status ? 'OLT activada correctamente.' : 'OLT desactivada correctamente.',
                'data' => [
                    'id' => $olt->id,
                    'status' => $olt->status
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error("Error cambiando status de OLT {$olt->id}", [
                'error' => $e->getMessage()
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado.'
            ], 500);
        }
    }
    
}
