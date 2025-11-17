<?php

namespace App\Services;

use App\Models\CommandAction;
use App\Models\OLT;
use App\Models\ObtainedCustomer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OltCommandService
{
    private const DEFAULT_COMMAND = 'show running-config';

    /**
     * Ejecuta múltiples comandos y devuelve el array 'results' o null
     */
    private function executeCommands(OLT $olt, array $commands, int $timeout = 60): ?array
    {
        try {
            $payload = $this->buildPayload($olt, $commands);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->retry(2, 100)->timeout($timeout)->post("http://x8okocwgko08ooswggoko4g4.172.16.255.5.sslip.io/execute", $payload);

            if ($response->successful()) {
                return $response->json()['results'] ?? [];
            }

            Log::error('OLT executeCommands failed', [
                'olt_id' => $olt->id,
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error('Exception in executeCommands', [
                'olt_id' => $olt->id,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Execute show running-config command on an OLT
     */
    public function executeRunningConfig(OLT $olt): ?string
    {
        try {
            $results = $this->executeCommands($olt, [self::DEFAULT_COMMAND], 90);
            return $results[0]['output'] ?? null;
        } catch (\Exception $e) {
            Log::error('Exception during OLT command execution', [
                'olt_id' => $olt->id,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Get complete ONU information including speed and admin state
     */
    public function getOnuCompleteInfo(OLT $olt, string $gponInterface): array
    {
        try {
            $commands = [
                "show running-config interface gpon-onu_$gponInterface",
                "show gpon onu detail-info gpon-onu_$gponInterface"
            ];

            $results = $this->executeCommands($olt, $commands, 45);
            $configOutput = $results[0]['output'] ?? '';
            $detailOutput = $results[1]['output'] ?? '';

            return [
                'success' => true,
                'gpon_interface' => $gponInterface,
                'customer_name' => $this->extractCustomerName($configOutput),
                'speed_info' => $this->extractSpeedInfo($configOutput),
                'admin_state' => $this->extractAdminState($detailOutput, $configOutput),
                'operational_state' => $this->extractOperationalState($detailOutput),
                'raw_config' => $configOutput,
                'raw_detail' => $detailOutput
            ];
        } catch (\Exception $e) {
            Log::error('Exception getting ONU complete info', [
                'olt_id' => $olt->id,
                'gpon_interface' => $gponInterface,
                'error' => $e->getMessage()
            ]);
        }

        return [
            'success' => false,
            'gpon_interface' => $gponInterface,
            'customer_name' => null,
            'speed_info' => null,
            'admin_state' => false,
            'operational_state' => 'unknown',
            'error' => 'No se pudo obtener la información de la ONU'
        ];
    }

    /**
     * Extract customer name from config output
     */
    private function extractCustomerName(string $configOutput): ?string
    {
        if (preg_match('/^\s*name\s+(.+)$/im', $configOutput, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Extract speed information from a config section
     */
    private function extractSpeedInfo(string $configSection): ?string
    {
        $downstreams = [];
        
        // Patrón único para traffic-limit
        if (preg_match_all('/traffic-limit\s+upstream\s+(\d+(?:\.\d+)?)([MG])?bps\s+downstream\s+(\d+(?:\.\d+)?)([MG])?bps/i', $configSection, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $downValue = (float)$m[3];
                $downUnit = strtoupper($m[4] ?? 'M');
                $downInMbps = ($downUnit === 'G') ? $downValue * 1000 : $downValue;
                $downstreams[] = $downInMbps;
            }
        }
        
        // Patrón para downstream simple
        if (preg_match_all('/downstream\s+(\d+(?:\.\d+)?)([MG])?/i', $configSection, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $downValue = (float)$m[1];
                $downUnit = strtoupper($m[2] ?? 'M');
                $downInMbps = ($downUnit === 'G') ? $downValue * 1000 : $downValue;
                $downstreams[] = $downInMbps;
            }
        }

        if (!empty($downstreams)) {
            $maxDown = max($downstreams);
            if ($maxDown >= 1000) {
                $g = $maxDown / 1000;
                return rtrim(rtrim(number_format($g, 3, '.', ''), '0'), '.') . 'Gbps';
            } else {
                return intval($maxDown) . 'Mbps';
            }
        }

        return null;
    }

    /**
     * Extract admin state from detail output and config section
     */
    private function extractAdminState(string $detailOutput, string $configSection): bool
    {
        $detail = trim($detailOutput);
        $config = trim($configSection);

        // Buscar en detailOutput primero
        if (!empty($detail)) {
            if (preg_match('/admin[\s\-]*state\s*[:=]?\s*(enable|enabled|up|active)/i', $detail)) {
                return true;
            }
            if (preg_match('/admin[\s\-]*state\s*[:=]?\s*(disable|disabled|down|inactive)/i', $detail)) {
                return false;
            }
            if (preg_match('/onu\s*status\s*[:=]?\s*(enable|enabled|up)/i', $detail)) {
                return true;
            }
            if (preg_match('/onu\s*status\s*[:=]?\s*(disable|disabled|down)/i', $detail)) {
                return false;
            }
        }

        // Buscar en configSection
        if (!empty($config)) {
            if (preg_match('/^\s*no\s+shutdown\s*$/im', $config)) {
                return true;
            }
            if (preg_match('/^\s*shutdown\s*$/im', $config)) {
                return false;
            }
            if (preg_match('/admin[\s\-]*state\s*[:=]?\s*(enable|enabled|up)/i', $config)) {
                return true;
            }
            if (preg_match('/name\s+.+|gemport\s+/i', $config)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract operational state from detail output
     */
    private function extractOperationalState(string $detailOutput): string
    {
        if (preg_match('/(operation|operational|state|status)\s*(state|status)?\s*[:=]\s*([a-zA-Z0-9_-]+)/i', $detailOutput, $matches)) {
            return strtolower($matches[3]);
        }
        return 'unknown';
    }

    /**
     * Build payload for OLT API
     * NOTE: Ya no se usa la relación 'model'. Se toman directamente los campos del OLT
     */
    private function buildPayload(OLT $olt, array $commands): array
    {
        return [
            'device' => [
                'ip_address' => $olt->ip_olt,
                // Usar directamente username/password en OLT; fallback a string si no existe
                'username' => $olt->username ?? 'string',
                'password' => $olt->password ?? 'string',
                'enable_password' => $olt->enable_password ?? 'string',
                'timeout' => 25,
                'retries' => 2,
                'connection_type' => 'telnet',
                // fallback de puerto a 23 si no está definido en el registro OLT
                'port' => $olt->port ?? 23
            ],
            'commands' => $commands
        ];
    }

    /**
     * Get simplified ONU info
     */
    public function getSimpleOnuInfo(OLT $olt, string $gponInterface): array
    {
        try {
            $commands = ["show running-config interface gpon-onu_$gponInterface"];
            $results = $this->executeCommands($olt, $commands, 30);
            $configOutput = $results[0]['output'] ?? '';

            if (empty($configOutput) || preg_match('/(Invalid|not found|No such)/i', $configOutput)) {
                return [
                    'success' => false,
                    'error' => 'Interfaz no encontrada o comando inválido'
                ];
            }

            $customerName = $this->extractCustomerName($configOutput);
            if (!$customerName) {
                return [
                    'success' => false,
                    'error' => 'ONU sin configuración de cliente'
                ];
            }

            return [
                'success' => true,
                'gpon_interface' => $gponInterface,
                'customer_name' => $customerName,
                'speed_info' => $this->extractSpeedInfo($configOutput),
                'admin_state' => $this->extractAdminState('', $configOutput),
                'raw_config' => $configOutput
            ];
        } catch (\Exception $e) {
            Log::error('Error in getSimpleOnuInfo', [
                'olt_id' => $olt->id,
                'gpon_interface' => $gponInterface,
                'error' => $e->getMessage()
            ]);
        }

        return [
            'success' => false,
            'gpon_interface' => $gponInterface,
            'error' => 'Error de conexión con la OLT'
        ];
    }

    /**
     * Process all active OLTs and extract customer data with complete info
     */
    public function processAllActiveOlts(): array
    {
        $results = [
            'processed_olts' => 0,
            'total_customers_found' => 0,
            'customers_updated' => 0,
            'customers_created' => 0,
            'errors' => []
        ];

        // Ya no se incluye with('model')
        $activeOlts = OLT::active()->get();
        Log::info("Found {$activeOlts->count()} active OLTs to process");

        foreach ($activeOlts as $olt) {
            try {
                Log::info("Processing OLT: {$olt->name} (ID: {$olt->id})");

                $configOutput = $this->executeRunningConfig($olt);
                if (!$configOutput) {
                    $results['errors'][] = "Failed to get config from OLT: {$olt->name}";
                    $results['processed_olts']++;
                    continue;
                }

                $customers = $this->parseCustomersFromConfig($configOutput, $olt);
                $results['total_customers_found'] += count($customers);

                if (empty($customers)) {
                    $results['processed_olts']++;
                    continue;
                }

                // Procesar en chunks más grandes para mejor rendimiento
                $gponList = array_column($customers, 'gpon_interface');
                $detailOutputs = [];
                $chunks = array_chunk($gponList, 80); // Aumentado de 60 a 80

                foreach ($chunks as $chunk) {
                    $detailCommands = array_map(fn($gpon) => "show gpon onu detail-info gpon-onu_$gpon", $chunk);
                    $chunkResults = $this->executeCommands($olt, $detailCommands, 75);
                    
                    if ($chunkResults !== null) {
                        foreach ($chunk as $index => $gpon) {
                            $detailOutputs[$gpon] = $chunkResults[$index]['output'] ?? '';
                        }
                    }
                }

                // Procesar clientes
                foreach ($customers as $customerData) {
                    $gpon = $customerData['gpon_interface'];
                    $detail = $detailOutputs[$gpon] ?? '';
                    
                    $customerData = array_merge($customerData, [
                        'speed' => $this->extractSpeedInfo($customerData['raw_config_section']),
                        'status' => $this->extractAdminState($detail, $customerData['raw_config_section']),
                        'last_updated_at' => Carbon::now(),
                        'raw_detail' => $detail
                    ]);

                    $this->updateOrCreateCustomer($customerData, $results);
                }

                $results['processed_olts']++;
            } catch (\Exception $e) {
                $error = "Error processing OLT {$olt->name}: " . $e->getMessage();
                Log::error($error);
                $results['errors'][] = $error;
            }
        }

        Log::info("OLT processing completed", $results);
        return $results;
    }

    /**
     * Update or create customer record
     */
    private function updateOrCreateCustomer(array $customerData, array &$results): void
    {
        $existingCustomer = ObtainedCustomer::where('olt_id', $customerData['olt_id'])
            ->where('gpon_interface', $customerData['gpon_interface'])
            ->first();

        if ($existingCustomer) {
            $existingCustomer->update($customerData);
            $results['customers_updated']++;
        } else {
            try {
                ObtainedCustomer::create($customerData);
                $results['customers_created']++;
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    Log::warning("Duplicate customer entry skipped: {$customerData['customer_name']}");
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * Parse running config output to extract customer information
     */
    public function parseCustomersFromConfig(string $configOutput, OLT $olt): array
    {
        $customers = [];
        $lines = explode("\n", $configOutput);

        $currentInterface = null;
        $currentName = null;
        $currentSection = [];
        $inInterfaceSection = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^interface\s+gpon-onu_(\S+)/i', $trimmed, $matches)) {
                // Guardar sección anterior si existe
                if ($currentInterface && $currentName) {
                    $customers[] = [
                        'olt_id' => $olt->id,
                        'gpon_interface' => $currentInterface,
                        'customer_name' => $currentName,
                        'raw_config_section' => implode("\n", $currentSection)
                    ];
                }

                $currentInterface = $matches[1];
                $currentName = null;
                $currentSection = [$trimmed];
                $inInterfaceSection = true;
                continue;
            }

            if ($inInterfaceSection) {
                $currentSection[] = $trimmed;

                // Detectar fin de sección
                if ($trimmed && !preg_match('/^\s/', $line) && !preg_match('/^interface\s+/i', $trimmed)) {
                    if ($currentInterface && $currentName) {
                        $customers[] = [
                            'olt_id' => $olt->id,
                            'gpon_interface' => $currentInterface,
                            'customer_name' => $currentName,
                            'raw_config_section' => implode("\n", $currentSection)
                        ];
                    }
                    $currentInterface = $currentName = null;
                    $currentSection = [];
                    $inInterfaceSection = false;
                } elseif (preg_match('/^\s*name\s+(.+)$/i', $trimmed, $nameMatches)) {
                    $currentName = trim($nameMatches[1]);
                }
            }
        }

        // Última sección
        if ($currentInterface && $currentName) {
            $customers[] = [
                'olt_id' => $olt->id,
                'gpon_interface' => $currentInterface,
                'customer_name' => $currentName,
                'raw_config_section' => implode("\n", $currentSection)
            ];
        }

        return $customers;
    }
}
