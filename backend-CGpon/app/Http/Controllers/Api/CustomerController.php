<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\GeneralHelper;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\ISP;
use App\Models\OLT;
use App\Services\CustomerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customerService)
    {
    }

    /**
     * Display a listing of the resource (API).
     */
    public function index(): JsonResponse
    {
       /**
         * Los clientes los debe mandar a crear inicialmente el isp representative
         * Entonces este no debe ver los demas isp ya que los que cree seran del isp que tiene asignado
         * Y podrá ver solo las olt que tenga su respectivo isp.
         *
         * Luego el main_provider debe rellenar el gpon_interface nada mas, entonces el no podrá acceder al formulario de guardado, solo al de actualizar
         *
         * El superadmin como puede hacer todo puede acceder a todo
         *
         */
        $user_type = GeneralHelper::get_user_type_code();

        // Si es superadmin puede observar
        if ($user_type == 'superadmin') {
            $olts = OLT::join('isp_olt', 'olts.id', '=', 'isp_olt.olt_id')
                ->join('isps', 'isps.id', '=', 'isp_olt.isp_id')
                ->select(
                    'olts.id as id',
                    'olts.name as label',
                    'isps.id as isp_id'
                )
                ->get();
            // $olts->prepend(['olt_id' => 4, 'title' => 'abc', 'category' => 'abc']);
        } else if ($user_type == 'isp_representative') {
            $olts = OLT::join('isp_olt', 'olts.id', '=', 'isp_olt.olt_id')
                ->where('isp_olt.isp_id', request()->user()->isp_id)
                ->select('olts.id', 'olts.name as label')
                ->get();
            // $olts->prepend(['id' => '', 'label' => 'Seleccione una opción']);
        } else {
            $olts = null;
        }

        if ($user_type == 'superadmin' ) { //|| $user_type == 'main_provider'
            $isps = ISP::active()->select('id', 'name as label')->get();
        } else {
            $isps = null;
        }

        // Logica del index (mostrar la tabla)
        $customers = Customer::select(
            'customers.id',
            'customers.gpon_interface',
            'customers.service_number',
            'customers.code_customer',
            'customers.customer_name',
            'customers.obtained_velocity',
            'olts.name as olt_name',
            'olts.id as olt_id',
            'isps.id as isp_id',
            'isps.name as isp_name',
            DB::raw('CASE WHEN customers.obtained_status = 1 THEN "Activo" ELSE "Suspendido" END as status')
        )
        ->leftJoin('olts', 'customers.olt_id', '=', 'olts.id')
        ->leftJoin('isps', 'customers.isp_id', '=', 'isps.id');

        // If the user is from a ISP, he can only see customers that belongs to that ISP
        if ($user_type == 'isp_representative') {
            $customers->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('isp_olt')
                    ->whereColumn('isp_olt.olt_id', 'olts.id')
                    ->where('isp_olt.isp_id', request()->user()->isp_id);
            });
        }

        // Log the SQL query for debugging
        \Log::debug('Customers Query', [
            'sql' => $customers->toSql(),
            'bindings' => $customers->getBindings()
        ]);

        $customers = $customers->get();

        // Log the results
        \Log::debug('Customers Results', [
            'count' => $customers->count(),
            'unique_ids' => $customers->pluck('id')->unique()->count(),
            'first_few' => $customers->take(3)->toArray()
        ]);

        // dd($customers);
    
        return response()->json([
            'olts' => $olts,
            'isps' => $isps,
            'customers' => $customers,
        ]);
    }
    
    /**
     * Store a newly created resource in storage (API).
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $customer = Customer::create($validated);

            if (!empty($validated['comment'])) {
                $customer->activity_log()->create([
                    'user_id' => $request->user()->id,
                    'action_type' => 'Comentario',
                    'content' => $validated['comment'],
                    'start_time' => Carbon::now(),
                ]);
            }

            DB::commit();

            // Recargar relaciones si es necesario
            $customer->load('olt');

            return response()->json([
                'success' => true,
                'message' => 'Cliente creado correctamente.',
                'data' => $customer,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error store Customer', ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource (API).
     */
    public function show(Customer $customer): JsonResponse
    {
        // Cargar relaciones
        $customer->load(['olt', 'activityLogs.user']);

        $history = $customer->activityLogs
        ? $customer->activityLogs
            ->sortByDesc('created_at') // ordenar primero
            ->map(function ($log) {
                return [
                    'action' => $log->action,
                    'user'   => $log->user ? $log->user->name : null,
                    'date'   => $log->created_at->format('Y-m-d H:i:s'),
                    'value'  => $log->value,
                ];
            })
            ->values() // reindexar
        : collect();
    

        return response()->json([
            'success' => true,
            'data'    => $customer,
            'history' => $history,
        ], 200);
    }

    

    /**
     * Show the form for editing the specified resource.
     * For API we just return the customer data (same as show).
     */
    public function edit(Customer $customer): JsonResponse
    {
        $customer->load(['olt']);

        if ($customer->olt) {
            $isp = DB::table('isps')
                ->join('isp_olt', 'isps.id', '=', 'isp_olt.isp_id')
                ->where('isp_olt.olt_id', $customer->olt_id)
                ->select('isps.id as isp_id', 'isps.name as isp_name')
                ->first();

            if ($isp) {
                $customer->isp_id = $isp->isp_id;
                $customer->isp_name = $isp->isp_name;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $customer,
        ], 200);
    }

    /**
     * Update the specified resource in storage (API).
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $customer->update($validated);

            if (!empty($validated['comment'])) {
                $customer->activity_log()->create([
                    'user_id' => $request->user()->id,
                    'action_type' => 'General',
                    'content' => $validated['comment'],
                    'start_time' => Carbon::now(),
                ]);
            }

            DB::commit();

            $customer->refresh()->load('olt');

            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado exitosamente.',
                'data' => $customer,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error update Customer', ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (API).
     */
    public function destroy(Customer $customer): JsonResponse
    {
        try {
            DB::beginTransaction();

            $customer->delete();

            ActivityLog::create([
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'action' => 'delete',
                'value' => null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cliente eliminado correctamente.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error destroy Customer', ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get status from OLT (API).
     */
    public function status(Customer $customer): JsonResponse
    {
        $gpon_interface = $customer->gpon_interface;
        $olt = $customer->olt;

        if (!$olt || !$olt->ip_olt) {
            return response()->json(['success' => false, 'message' => 'Cliente u OLT inválida'], 422);
        }

        $payload = [
            'device' => [
                'ip_address' => $olt->ip_olt,
                'username' => env('OLT_USERNAME', 'gpon_itel'),
                'password' => env('OLT_PASSWORD', 'itel2025..'),
                'enable_password' => env('OLT_ENABLE_PASSWORD', 'string'),
                'timeout' => 10,
                'retries' => 3,
                'connection_type' => 'telnet',
                'port' => 23
            ],
            'commands' => [
                "show gpon onu detail-info gpon-onu_$gpon_interface"
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(60)
              ->post(env('OLT_API_URL', 'http://x8okocwgko08ooswggoko4g4.172.16.255.5.sslip.io/execute'), $payload);

            if ($response->successful()) {
                $data = $response->json();
                $result = array_key_exists('results', $data) && is_array($data['results']) && !empty($data['results']) && array_key_exists('output', $data['results'][0])
                    ? $data['results'][0]['output']
                    : null;

                return response()->json(['success' => true, 'status_text' => $result], 200);
            }

            Log::error('API request failed', ['status' => $response->status(), 'body' => $response->body()]);

            return response()->json(['success' => false, 'message' => 'Error de conexión: No se pudo conectar con la OLT para obtener el estado.'], 502);
        } catch (\Throwable $e) {
            Log::error('API connection error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error de conexión: No se pudo conectar con la OLT para obtener el estado.'], 502);
        }
    }

    /**
     * Helpers para formateo de velocidad.
     */
    private function formatSpeed($mbps): string
    {
        return $mbps >= 1000 ? ($mbps / 1000) . ' Gbps' : $mbps . ' Mbps';
    }

    /**
     * Obtener velocidad actual del cliente (API).
     */
    public function getSpeed(Customer $customer): JsonResponse
    {
        $olt = $customer->olt;
        $gpon_interface = $customer->gpon_interface;
    
        if (!$olt || !$olt->ip_olt) {
            return response()->json(['success' => false, 'message' => 'Cliente u OLT inválida'], 422);
        }
    
        $olt_type = strtolower($olt->model->name ?? 'zte');
        $olt_type = str_contains($olt_type, 'huawei') ? 'huawei' : 'zte';
    
        $commands = $olt_type === 'zte'
            ? ["show running-config interface gpon-onu_$gpon_interface"]
            : [
                "display service-port " . ($customer->service_port ?? 48),
                "display traffic table ip from-index 0"
            ];
    
        $payload = [
            'device' => [
                'ip_address' => $olt->ip_olt,
                'username' => env('OLT_USERNAME', 'gpon_itel'),
                'password' => env('OLT_PASSWORD', 'itel2025..'),
                'enable_password' => env('OLT_ENABLE_PASSWORD', 'string'),
                'timeout' => 15,
                'retries' => 2,
                'connection_type' => 'telnet',
                'port' => 23
            ],
            'commands' => $commands
        ];
    
        try {
            $response = Http::timeout(15)->post(env('OLT_API_URL', 'http://x8okocwgko08ooswggoko4g4.172.16.255.5.sslip.io/execute'), $payload);
    
            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'No se pudo ejecutar comandos en la OLT'], 502);
            }
    
            $results = $response->json()['results'] ?? [];
            $configOutput = $results[0]['output'] ?? '';
    
            $lines = explode("\n", $configOutput);
            $gemports = [];
            $customerName = '';
    
            // Primero: recolectar TODOS los gemports
            foreach ($lines as $line) {
                $line = trim($line);
    
                if (!$customerName && str_contains($line, 'name ')) {
                    $parts = explode('name ', $line, 2);
                    if (count($parts) > 1) $customerName = trim($parts[1]);
                }
    
                // Buscar TODOS los traffic-limit de TODOS los gemports
                if (strpos($line, 'traffic-limit') !== false) {
                    // Patrón para cualquier gemport
                    if (preg_match('/gemport\s+(\d+).*traffic-limit\s+upstream\s+(\d+(?:\.\d+)?)([MG])?bps\s+downstream\s+(\d+(?:\.\d+)?)([MG])?bps/i', $line, $matches)) {
                        $gemport = $matches[1];
                        $upstreamValue = floatval($matches[2]);
                        $upstreamUnit = strtoupper($matches[3] ?? 'M');
                        $downstreamValue = floatval($matches[4]);
                        $downstreamUnit = strtoupper($matches[5] ?? 'M');
                        
                        // Convertir a Mbps si es Gbps
                        if ($upstreamUnit === 'G') {
                            $upstreamValue *= 1000;
                        }
                        if ($downstreamUnit === 'G') {
                            $downstreamValue *= 1000;
                        }
                        
                        $gemports[$gemport] = [
                            'gemport' => $gemport,
                            'upstream' => $matches[2] . $matches[3] . 'bps',
                            'downstream' => $matches[4] . $matches[5] . 'bps',
                            'upstream_mbps' => $upstreamValue,
                            'downstream_mbps' => $downstreamValue,
                            'raw' => $line
                        ];
                    }
                    // Patrón alternativo sin gemport explícito
                    elseif (preg_match('/traffic-limit\s+upstream\s+(\d+(?:\.\d+)?)([MG])?bps\s+downstream\s+(\d+(?:\.\d+)?)([MG])?bps/i', $line, $matches)) {
                        // Asignar a un gemport "unknown" y luego intentaremos mapearlo
                        $upstreamValue = floatval($matches[1]);
                        $upstreamUnit = strtoupper($matches[2] ?? 'M');
                        $downstreamValue = floatval($matches[3]);
                        $downstreamUnit = strtoupper($matches[4] ?? 'M');
                        
                        if ($upstreamUnit === 'G') {
                            $upstreamValue *= 1000;
                        }
                        if ($downstreamUnit === 'G') {
                            $downstreamValue *= 1000;
                        }
                        
                        $gemports['unknown'] = [
                            'gemport' => 'unknown',
                            'upstream' => $matches[1] . $matches[2] . 'bps',
                            'downstream' => $matches[3] . $matches[4] . 'bps',
                            'upstream_mbps' => $upstreamValue,
                            'downstream_mbps' => $downstreamValue,
                            'raw' => $line
                        ];
                    }
                }
            }
    
            // Identificar el servicio principal
            $mainGemport = $this->identifyMainService($gemports, $lines);
    
            // Usar los valores del servicio principal
            $foundUp = null;
            $foundDown = null;
            
            if ($mainGemport && isset($gemports[$mainGemport])) {
                $foundUp = $gemports[$mainGemport]['upstream_mbps'];
                $foundDown = $gemports[$mainGemport]['downstream_mbps'];
            } elseif (!empty($gemports)) {
                // Fallback: usar el primer gemport disponible
                $firstGemport = array_key_first($gemports);
                $foundUp = $gemports[$firstGemport]['upstream_mbps'];
                $foundDown = $gemports[$firstGemport]['downstream_mbps'];
                $mainGemport = $firstGemport;
            }
    
            // Construir output con todos los gemports
            $outputConfig = '';
            foreach ($gemports as $gemport => $data) {
                $highlight = ($gemport == $mainGemport);
                $outputConfig .= "Gemport {$gemport}{$highlight}:\n  • Subida: {$data['upstream']} ({$data['upstream_mbps']} Mbps)\n  • Bajada: {$data['downstream']} ({$data['downstream_mbps']} Mbps)\n\n";
            }
            $outputConfig .= str_repeat("-", 50) . "\nConfiguración completa:\n\n" . $configOutput;
    
            // Función para formatear la velocidad
            $formatSpeed = function ($speedInMbps) {
                if ($speedInMbps >= 1000) {
                    return round($speedInMbps / 1000, 1) . ' Gbps';
                }
                return round($speedInMbps) . ' Mbps';
            };
    
            return response()->json([
                'success' => true,
                'olt_type' => $olt_type,
                'summary_up' => $foundUp !== null ? $formatSpeed($foundUp) : 'N/A',
                'summary_down' => $foundDown !== null ? $formatSpeed($foundDown) : 'N/A',
                'speed_info' => $outputConfig,
                'raw_up_mbps' => $foundUp,
                'raw_down_mbps' => $foundDown,
                'debug_note' => $mainGemport ? 'Servicio principal: Gemport ' . $mainGemport : 'No se identificó servicio principal',
                'all_gemports' => array_keys($gemports) // Para debug
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error getSpeed', ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Error de conexión', 'detail' => $e->getMessage()], 500);
        }
    }
    
    private function identifyMainService(array $gemports, array $lines): ?string
    {
        // Si solo hay un gemport, usarlo
        if (count($gemports) === 1) {
            return array_key_first($gemports);
        }

        // Estrategia 1: Buscar gemport con nombre "INTERNET" (máxima prioridad)
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (preg_match('/gemport\s+(\d+).*name\s+INTERNET/i', $line, $matches)) {
                $gemport = $matches[1];
                if (isset($gemports[$gemport])) {
                    return $gemport;
                }
            }
        }

        // Estrategia 2: Buscar gemport con MAYOR velocidad (segunda prioridad)
        $maxSpeed = 0;
        $mainGemportBySpeed = null;
        
        foreach ($gemports as $gemport => $data) {
            $totalSpeed = $data['upstream_mbps'] + $data['downstream_mbps'];
            
            // Solo considerar velocidades mayores a 10Mbps (para excluir gestión)
            if ($totalSpeed > 10 && $totalSpeed > $maxSpeed) {
                $maxSpeed = $totalSpeed;
                $mainGemportBySpeed = $gemport;
            }
        }

        // Estrategia 3: Buscar gemport con nombre "DATOS" pero solo si no hay uno de mayor velocidad
        if ($mainGemportBySpeed) {
            // Verificar si el gemport de mayor velocidad tiene un nombre específico
            foreach ($lines as $line) {
                $line = trim($line);
                
                if (preg_match('/gemport\s+(\d+).*name\s+DATOS/i', $line, $matches)) {
                    $datosGemport = $matches[1];
                    // Si el gemport "DATOS" tiene al menos el 80% de la velocidad máxima, usarlo
                    if (isset($gemports[$datosGemport])) {
                        $datosSpeed = $gemports[$datosGemport]['upstream_mbps'] + $gemports[$datosGemport]['downstream_mbps'];
                        if ($datosSpeed >= ($maxSpeed * 0.8)) { // Si tiene al menos 80% de la velocidad máxima
                            return $datosGemport;
                        }
                    }
                }
            }
            
            // Si no hay un "DATOS" con velocidad comparable, usar el de mayor velocidad
            return $mainGemportBySpeed;
        }

        // Estrategia 4: Buscar cualquier gemport con nombre "DATOS" como fallback
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (preg_match('/gemport\s+(\d+).*name\s+DATOS/i', $line, $matches)) {
                $gemport = $matches[1];
                if (isset($gemports[$gemport])) {
                    return $gemport;
                }
            }
        }

        // Estrategia 5: Último recurso - usar el gemport con mayor velocidad sin filtros
        $maxSpeed = 0;
        $mainGemport = null;
        foreach ($gemports as $gemport => $data) {
            $totalSpeed = $data['upstream_mbps'] + $data['downstream_mbps'];
            if ($totalSpeed > $maxSpeed) {
                $maxSpeed = $totalSpeed;
                $mainGemport = $gemport;
            }
        }

        return $mainGemport ?: array_key_first($gemports);
    }
    /**
     * Change speed for a customer (API).
     */
    public function changeSpeed(Request $request, Customer $customer): JsonResponse
    {
        set_time_limit(180);

        $olt = $customer->olt;
        $gpon_interface = $customer->gpon_interface;

        if (!$olt || !$olt->ip_olt) {
            return response()->json(['success' => false, 'message' => 'Cliente u OLT inválida'], 422);
        }

        $up = $request->input('up');
        $down = $request->input('down');
        $gemport = $request->input('gemport');

        if (!is_numeric($up) || !is_numeric($down)) {
            return response()->json(['success' => false, 'message' => 'Valores de up/down inválidos'], 422);
        }

        $olt_type = strtolower($olt->model->name ?? 'zte');
        $olt_type = str_contains($olt_type, 'huawei') ? 'huawei' : 'zte';

        $service_port = $customer->service_port ?? 48;

        $parts = explode(':', $gpon_interface);
        $gpon_port = $parts[0] ?? '';
        $ont_id = $parts[1] ?? '';

        if ($olt_type === 'zte') {
            $commands = [
                "configure terminal",
                "interface gpon-onu_{$gpon_interface}",
                "gemport {$gemport} traffic-limit upstream {$up}Mbps downstream {$down}Mbps",
            ];
        } else {
            $up_kbps = $up * 1024;
            $down_kbps = $down * 1024;
            $dba_id = 20;
            $up_table_id = 900;
            $down_table_id = 901;

            $commands = [
                "configure",
                "undo dba-profile profile-id {$dba_id}",
                "dba-profile add profile-id {$dba_id} profile-name \"custom_up_{$up}\" type3 assure {$up_kbps} max {$up_kbps}",
                "undo traffic table ip index {$up_table_id}",
                "traffic table ip index {$up_table_id} name \"custom_up_{$up}\" cir {$up_kbps} pir {$up_kbps} priority 0 priority-policy local-setting",
                "undo traffic table ip index {$down_table_id}",
                "traffic table ip index {$down_table_id} name \"custom_down_{$down}\" cir {$down_kbps} pir {$down_kbps} priority 0 priority-policy local-setting",
                "interface gpon {$gpon_port}",
                "ont modify {$ont_id} tcont 1 dba-profile-id {$dba_id}",
                "quit",
                "service-port {$service_port} inbound traffic-table index {$up_table_id} outbound traffic-table index {$down_table_id}",
                "commit",
                "quit"
            ];
        }

        $payload = [
            'device' => [
                'ip_address' => $olt->ip_olt,
                'username' => env('OLT_USERNAME', 'gpon_itel'),
                'password' => env('OLT_PASSWORD', 'itel2025..'),
                'enable_password' => env('OLT_ENABLE_PASSWORD', 'string'),
                'timeout' => 15,
                'retries' => 2,
                'connection_type' => 'telnet',
                'port' => 23
            ],
            'commands' => $commands
        ];

        try {
            $response = Http::withOptions(['timeout' => 120, 'connect_timeout' => 10])->retry(2, 1000)
                ->post(env('OLT_API_URL', 'http://x8okocwgko08ooswggoko4g4.172.16.255.5.sslip.io/execute'), $payload);

            $body = $response->json();

            Log::debug("Comandos enviados a OLT {$olt->ip_olt}", $commands);
            Log::debug("Respuesta cruda OLT", ['body' => $response->body()]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo aplicar la velocidad',
                    'raw_output' => substr($response->body(), 0, 2000)
                ], 502);
            }

            // Actualizamos info en BD (sincrónico)
            $this->updateSpeed($customer);

            ActivityLog::create([
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'action' => 'cambiar velocidad',
                'value' => "Velocidad Up: {$up} Mbps / Down: {$down} Mbps",
            ]);

            return response()->json([
                'success' => true,
                'olt_type' => $olt_type,
                'commands' => $commands,
                'message' => "Velocidad solicitada Up: {$up} Mbps / Down: {$down} Mbps",
                'raw' => $body
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error en changeSpeed', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error de conexión', 'detail' => $e->getMessage()], 500);
        }
    }

    /**
     * activar client (API).
     */
    public function activarCliente(Customer $customer): JsonResponse
    {
        $olt = $customer->olt;
        $gpon_interface = $customer->gpon_interface;

        if (!$olt || !$olt->ip_olt) {
            return response()->json(['success' => false, 'message' => 'Cliente u OLT inválida'], 422);
        }

        $olt_type = strtolower($olt->model->name ?? 'zte');
        $olt_type = str_contains($olt_type, 'huawei') ? 'huawei' : 'zte';

        $commands = $olt_type === 'zte'
            ? ["configure terminal", "interface gpon-onu_$gpon_interface", "no shutdown", "exit"]
            : ["service-port " . ($customer->service_port ?? 48) . " adminstatus enable"];

        $payload = [
            'device' => [
                'ip_address' => $olt->ip_olt,
                'username' => env('OLT_USERNAME', 'gpon_itel'),
                'password' => env('OLT_PASSWORD', 'itel2025..'),
                'enable_password' => env('OLT_ENABLE_PASSWORD', 'string'),
                'timeout' => 15,
                'retries' => 2,
                'connection_type' => 'telnet',
                'port' => 23
            ],
            'commands' => $commands
        ];

        try {
            $response = Http::timeout(60)->post(env('OLT_API_URL', 'http://x8okocwgko08ooswggoko4g4.172.16.255.5.sslip.io/execute'), $payload);

            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'No se pudo ejecutar comandos en la OLT'], 502);
            }

            $this->updateStatus($customer);

            ActivityLog::create([
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'action' => 'activar',
                'value' => null,
            ]);

            return response()->json([
                'success' => true,
                'olt_type' => $olt_type,
                'message' => 'Cliente activado correctamente',
                'raw_output' => substr(collect($response->json()['results'] ?? [])->pluck('output')->implode("\n"), 0, 2000)
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error activarClient', ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Error de conexión', 'detail' => $e->getMessage()], 500);
        }
    }

    /**
     * suspender client (API).
     */
    public function suspenderCliente(Customer $customer): JsonResponse
    {
        $olt = $customer->olt;
        $gpon_interface = $customer->gpon_interface;

        if (!$olt || !$olt->ip_olt) {
            return response()->json(['success' => false, 'message' => 'Cliente u OLT inválida'], 422);
        }

        $olt_type = strtolower($olt->model->name ?? 'zte');
        $olt_type = str_contains($olt_type, 'huawei') ? 'huawei' : 'zte';

        $commands = $olt_type === 'zte'
            ? ["configure terminal", "interface gpon-onu_$gpon_interface", "shutdown", "exit"]
            : ["service-port " . ($customer->service_port ?? 48) . " adminstatus disable"];

        $payload = [
            'device' => [
                'ip_address' => $olt->ip_olt,
                'username' => env('OLT_USERNAME', 'gpon_itel'),
                'password' => env('OLT_PASSWORD', 'itel2025..'),
                'enable_password' => env('OLT_ENABLE_PASSWORD', 'string'),
                'timeout' => 15,
                'retries' => 2,
                'connection_type' => 'telnet',
                'port' => 23
            ],
            'commands' => $commands
        ];

        try {
            $response = Http::timeout(60)->post(env('OLT_API_URL', 'http://x8okocwgko08ooswggoko4g4.172.16.255.5.sslip.io/execute'), $payload);

            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'No se pudo ejecutar comandos en la OLT'], 502);
            }

            $this->updateStatus($customer);

            ActivityLog::create([
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'action' => 'suspender',
                'value' => null,
            ]);

            return response()->json([
                'success' => true,
                'olt_type' => $olt_type,
                'message' => 'Cliente suspendido correctamente',
                'raw_output' => substr(collect($response->json()['results'] ?? [])->pluck('output')->implode("\n"), 0, 2000)
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error suspenderClient', ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Error de conexión', 'detail' => $e->getMessage()], 500);
        }
    }

    /**
     * Force update status (calls service).
     */
    public function updateStatus(Customer $customer): JsonResponse
    {
        try {
            $statusService = new \App\Services\ObtainCustomersInfoService();
            $results = $statusService->processStatusCustomer($customer);

            return response()->json([
                'success' => true,
                'message' => 'El proceso de cambiar el estado del cliente se completó exitosamente.',
                'data' => $results,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error updateStatus', ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la actualizacion: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Force update speed (calls service).
     */
    public function updateSpeed(Customer $customer): JsonResponse
    {
        try {
            $statusService = new \App\Services\ObtainCustomersInfoService();
            $results = $statusService->processSpeedCustomer($customer);

            return response()->json([
                'success' => true,
                'message' => 'El proceso de cambiar la velocidad del cliente se completó exitosamente.',
                'data' => $results,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error updateSpeed', ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la actualizacion: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function completeStatus(Customer $customer): JsonResponse
    {
        $gpon_interface = $customer->gpon_interface;

        log::info("Interfaaaaaaz Gpon");
        log::info($gpon_interface);
        
        $customer->load('olt');
        $olt_ip = $customer->olt->ip_olt ?? '172.20.5.10';

        $payload = [
            'device' => [
                'ip_address' => $olt_ip,
                'username' => 'gpon_itel',
                'password' => 'itel2025..',
                'enable_password' => 'string',
                'timeout' => 15,
                'retries' => 3,
                'connection_type' => 'telnet',
                'port' => 23
            ],
            'commands' => [
                "show gpon onu detail-info gpon-onu_$gpon_interface",
                "show running-config interface gpon-onu_$gpon_interface",
                "show pon power attenuation gpon-onu_$gpon_interface"
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(120)
              ->post("http://x8okocwgko08ooswggoko4g4.172.16.255.5.sslip.io/execute", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $result = [
                    'status_text' => 'Error: No se pudo obtener el estado del cliente.',
                    'speed_info' => 'Error: No se pudo obtener la información de velocidad.',
                    'optical_power' => 'Error: No se pudo obtener la potencia óptica.'
                ];

                if (array_key_exists('results', $data) && is_array($data['results'])) {
                    $results = $data['results'];
                    if (isset($results[0]) && array_key_exists('output', $results[0])) {
                        $result['status_text'] = $results[0]['output'];
                    }
                    if (isset($results[1]) && array_key_exists('output', $results[1])) {
                        $result['speed_info'] = GeneralHelper::parseSpeedFromConfig($results[1]['output']);
                    }
                    if (isset($results[2]) && array_key_exists('output', $results[2])) {
                        $result['optical_power'] = $results[2]['output'];
                    }
                }

                return response()->json($result);
            }

            Log::error('API request failed for obtained customer complete status', [
                'status' => $response->status(),
                'body' => $response->body(),
                'obtained_customer_id' => $customer->id,
                'gpon_interface' => $gpon_interface,
                'olt_ip' => $olt_ip
            ]);

            return response()->json([
                'status_text' => 'Error: No se pudo obtener el estado del cliente. Verifique la conexión con la OLT.',
                'speed_info' => 'Error: No se pudo obtener la información de velocidad.',
                'optical_power' => 'Error: No se pudo obtener la potencia óptica.'
            ]);

        } catch (RequestException $e) {
            Log::error('API connection error for obtained customer complete status', [
                'error' => $e->getMessage(),
                'obtained_customer_id' => $customer->id,
                'gpon_interface' => $gpon_interface,
                'olt_ip' => $olt_ip
            ]);

            return response()->json([
                'status_text' => 'Error de conexión: No se pudo conectar con la OLT para obtener el estado.',
                'speed_info' => 'Error de conexión: No se pudo obtener la información de velocidad.',
                'optical_power' => 'Error de conexión: No se pudo obtener la potencia óptica.'
            ]);
        }
    }
}
