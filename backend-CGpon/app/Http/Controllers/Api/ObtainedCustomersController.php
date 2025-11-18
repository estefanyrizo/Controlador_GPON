<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ObtainedCustomer;
use App\Helpers\GeneralHelper;
use App\Models\Isp;
use App\Models\Olt;
use App\Jobs\ObtainCustomersJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\RequestException;

class ObtainedCustomersController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:superadmin,main_provider');
    }
    /**
     * List all obtained customers
     */
    public function index(): JsonResponse
    {
        $obtainedCustomers = ObtainedCustomer::with(['olt'])
            ->orderBy('last_updated_at', 'desc')
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'olt_name' => $customer->olt->name ?? 'N/A',
                    'olt_ip' => $customer->olt->ip_olt ?? 'N/A',
                    'gpon_interface' => $customer->gpon_interface,
                    'customer_name' => $customer->customer_name,
                    'last_updated_at' => $customer->last_updated_at,
                    'last_updated_at_formatted' => $customer->last_updated_at->format('d/m/Y H:i:s'),
                    'created_at' => $customer->created_at,
                    'updated_at' => $customer->updated_at,
                    'olt_id' => $customer->olt_id,
                    'raw_config_section' => $customer->raw_config_section,
                    'status' => $customer->status ? 'Activo' : 'Suspendido',
                    'speed' => $customer->speed,
                ];
            })
            ->values()
            ->toArray();

        $isps = Isp::all(['id', 'name']);
        $olts = Olt::all(['id', 'name', 'ip_olt'])->map(function ($olt) {
            return [
                'id' => $olt->id,
                'name' => $olt->name,
                'ip' => $olt->ip_olt,
            ];
        })->toArray();


        return response()->json([
            'obtainedCustomers' => $obtainedCustomers,
            'isps' => $isps,
            'olts' => $olts,
        ]);
    }

    /**
     * Manually trigger the customer data collection
     */
    public function collect(): JsonResponse
    {
        try {
            ObtainCustomersJob::dispatch();
            return response()->json([
                'success' => true,
                'message' => 'El proceso de recolección de datos de clientes ha sido iniciado.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar el proceso: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show details of a specific obtained customer
     */
    public function show(ObtainedCustomer $obtainedCustomer): JsonResponse
    {
        $obtainedCustomer->load('olt');

        return response()->json([
            'customer' => [
                'id' => $obtainedCustomer->id,
                'olt_name' => $obtainedCustomer->olt->name ?? 'N/A',
                'olt_ip' => $obtainedCustomer->olt->ip_olt ?? 'N/A',
                'gpon_interface' => $obtainedCustomer->gpon_interface,
                'customer_name' => $obtainedCustomer->customer_name,
                'raw_config_section' => $obtainedCustomer->raw_config_section,
                'last_updated_at' => $obtainedCustomer->last_updated_at,
                'last_updated_at_formatted' => $obtainedCustomer->last_updated_at->format('d/m/Y H:i:s'),
                'created_at' => $obtainedCustomer->created_at,
                'updated_at' => $obtainedCustomer->updated_at,
                'olt_id' => $obtainedCustomer->olt_id,
            ]
        ]);
    }

    /**
     * Check if a customer exists
     */
    public function checkExistence(Request $request): JsonResponse
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'gpon_interface' => 'required|string|max:255',
            'olt_id' => 'required|integer|exists:olts,id',
        ]);

        $exists = Customer::where([
            'customer_name' => $request->customer_name,
            'gpon_interface' => $request->gpon_interface,
            'olt_id' => $request->olt_id,
        ])->exists();

        if ($exists) {
            return response()->json([
                'exists' => true,
                'message' => 'El cliente ya fue asignado a una ISP.',
            ], 422);
        }

        return response()->json([
            'exists' => false,
            'message' => 'El cliente no existe, puede crearse',
        ]);
    }

    /**
     * Store a customer from ObtainedCustomer
     */
    public function storeFromObtained(ObtainedCustomer $obtainedCustomer, Request $request): JsonResponse
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'gpon_interface' => 'required|string|max:255',
            'olt_id' => 'required|integer|exists:olts,id',
            'isp_id' => 'nullable|integer|exists:isps,id',
        ]);

        $exists = Customer::where([
            'customer_name' => $request->customer_name,
            'gpon_interface' => $request->gpon_interface,
            'olt_id' => $request->olt_id,
        ])->exists();

        if ($exists) {
            return response()->json([
                'message' => 'El cliente ya existe en la base de datos',
            ], 422);
        }

        $customer = new Customer();
        $customer->customer_name = $request->customer_name;
        $customer->gpon_interface = $request->gpon_interface;
        $customer->olt_id = $request->olt_id;
        $customer->obtained_status = $obtainedCustomer->status;
        $customer->obtained_velocity = $obtainedCustomer->speed;

        $ispId = $request->input('isp_id');
        if ($ispId) {
            $customer->isp_id = $ispId;
        } elseif ($obtainedCustomer->olt) {
            $isp = DB::table('isps')
                ->join('isp_olt', 'isps.id', '=', 'isp_olt.isp_id')
                ->where('isp_olt.olt_id', $obtainedCustomer->olt_id)
                ->select('isps.id as isp_id')
                ->first();
            if ($isp) {
                $customer->isp_id = $isp->isp_id;
            }
        }

        $customer->save();

        return response()->json([
            'message' => 'Customer creado correctamente desde ObtainedCustomer',
            'customer' => $customer,
        ]);
    }

    public function storeMassiveFromObtained(Request $request): JsonResponse
    {
        $request->validate([
            'obtained_ids' => 'required|array|min:1',
            'obtained_ids.*' => 'integer|exists:obtained_customers,id',
            'isp_id' => 'nullable|integer|exists:isps,id',
        ]);

        $obtainedCustomers = ObtainedCustomer::whereIn('id', $request->obtained_ids)->get();
        $created = [];
        $skipped = [];

        foreach ($obtainedCustomers as $obtainedCustomer) {
            // Evitar duplicados
            $exists = Customer::where([
                'customer_name' => $obtainedCustomer->customer_name,
                'gpon_interface' => $obtainedCustomer->gpon_interface,
                'olt_id' => $obtainedCustomer->olt_id,
            ])->exists();

            if ($exists) {
                $skipped[] = $obtainedCustomer->id;
                continue;
            }

            $customer = new Customer();
            $customer->customer_name = $obtainedCustomer->customer_name;
            $customer->gpon_interface = $obtainedCustomer->gpon_interface;
            $customer->olt_id = $obtainedCustomer->olt_id;
            $customer->obtained_status = $obtainedCustomer->status;
            $customer->obtained_velocity = $obtainedCustomer->speed;

            // ISP por parámetro o automático
            if ($request->filled('isp_id')) {
                $customer->isp_id = $request->isp_id;
            } elseif ($obtainedCustomer->olt) {
                $isp = DB::table('isps')
                    ->join('isp_olt', 'isps.id', '=', 'isp_olt.isp_id')
                    ->where('isp_olt.olt_id', $obtainedCustomer->olt_id)
                    ->select('isps.id as isp_id')
                    ->first();

                if ($isp) {
                    $customer->isp_id = $isp->isp_id;
                }
            }

            $customer->save();
            $created[] = $customer->id;
        }

        return response()->json([
            'message' => 'Proceso masivo completado',
            'created_count' => count($created),
            'skipped_count' => count($skipped),
            'created_ids' => $created,
            'skipped_ids' => $skipped,
        ]);
    }


    public function status(ObtainedCustomer $obtainedCustomer): JsonResponse
    {
        $gpon_interface = $obtainedCustomer->gpon_interface;
        
        // Load the OLT relationship to get the IP address
        $obtainedCustomer->load('olt');
        $olt_ip = $obtainedCustomer->olt->ip_olt ?? '172.20.5.10';

        // Prepare the command payload for the network device
        $payload = [
            'device' => [
                'ip_address' => $olt_ip,
                'username' => 'gpon_itel',
                'password' => 'itel2025..',
                'enable_password' => 'string',
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
              ->post("http://x8okocwgko08ooswggoko4g4.172.16.255.5.sslip.io/execute", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $result = array_key_exists('results', $data) && is_array($data['results']) && !empty($data['results']) && array_key_exists('output', $data['results'][0])
                    ? $data['results'][0]['output']
                    : null;

                return response()->json([
                    'status_text' => $result,
                ]);
            }

            Log::error('API request failed for obtained customer status', [
                'status' => $response->status(),
                'body' => $response->body(),
                'obtained_customer_id' => $obtainedCustomer->id,
                'gpon_interface' => $gpon_interface,
                'olt_ip' => $olt_ip
            ]);

            return response()->json([
                'status_text' => 'Error: No se pudo obtener el estado del cliente. Verifique la conexión con la OLT.',
            ]);

        } catch (RequestException $e) {
            Log::error('API connection error for obtained customer status', [
                'error' => $e->getMessage(),
                'obtained_customer_id' => $obtainedCustomer->id,
                'gpon_interface' => $gpon_interface,
                'olt_ip' => $olt_ip
            ]);

            return response()->json([
                'status_text' => 'Error de conexión: No se pudo conectar con la OLT para obtener el estado.',
            ]);
        }
    }

     /**
     * Get the complete status of a specific obtained customer (status, speed, optical power)
     */
    public function completeStatus(ObtainedCustomer $obtainedCustomer): JsonResponse
    {
        $gpon_interface = $obtainedCustomer->gpon_interface;
        
        $obtainedCustomer->load('olt');
        $olt_ip = $obtainedCustomer->olt->ip_olt ?? '172.20.5.10';

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
                'obtained_customer_id' => $obtainedCustomer->id,
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
                'obtained_customer_id' => $obtainedCustomer->id,
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
