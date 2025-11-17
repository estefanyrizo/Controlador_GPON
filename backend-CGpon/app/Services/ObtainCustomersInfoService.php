<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ObtainCustomersInfoService
{
    public function processStatusCustomer($customer)
    {
        $results = [
            'updated' => 0,
            'errors' => 0,
        ];
        try {
            $status = $this->fetchStatus($customer);

            $dataToUpdate = [];
            $statusMap = [
                'enable' => true,
                'disable' => false,
            ];

            if ($status !== null && $status !== $customer->obtained_status)  {
                $dataToUpdate['obtained_status'] = $statusMap[$status];
            }

            if (!empty($dataToUpdate)) {
                $customer->update($dataToUpdate);
                $results['updated']++;
            }

            Log::info("Output de cliente {$customer->id}", [
                'status' => $status,
            ]);

        } catch (\Exception $e) {
            Log::error("Error obteniendo info de cliente {$customer->id}", [
                'error' => $e->getMessage()
            ]);
            $results['errors']++;
        }

        return $results;
    }

    private function fetchStatus(Customer $customer): ?string
    {
        $olt = $customer->olt;

        if (!$olt || !$olt->ip_olt) {
            return null;
        }

        $payload = [
            'device' => [
                'ip_address' => $olt->ip_olt,
                'username' => 'gpon_itel',
                'password' => 'itel2025..',
                'connection_type' => 'telnet',
                'port' => 23,
                'timeout' => 10,
            ],
            'commands' => [
                "show gpon onu detail-info gpon-onu_{$customer->gpon_interface}"
            ]
        ];

        try {
            $response = Http::timeout(60)->post("http://x8okocwgko08ooswggoko4g4.172.16.255.5.sslip.io/execute", $payload);

            if (!$response->successful()) {
                return null;
            }

            $output = $response->json()['results'][0]['output'] ?? '';

            $adminState = null;
            if (preg_match('/Admin state:\s*(\w+)/i', $output, $matches)) {
                $adminState = strtolower($matches[1]);
            }

            return $adminState;

        } catch (\Exception $e) {
            Log::error("Error obteniendo estado de cliente {$customer->id}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function processSpeedCustomer($customer)
    {
        $results = [
            'updated' => 0,
            'errors' => 0,
        ];
        try {
            $speed = $this->fetchSpeed($customer);

            $dataToUpdate = [];

            if ($speed !== null && $speed !== $customer->obtained_velocity) {
                $dataToUpdate['obtained_velocity'] = $speed;
            }

            if (!empty($dataToUpdate)) {
                $customer->update($dataToUpdate);
                $results['updated']++;
            }

            Log::info("Velocidad actualizada para el cliente {$customer->id}", [
                'speed' => $speed,
            ]);

        } catch (\Exception $e) {
            Log::error("Error obteniendo velocidad de cliente {$customer->id}", [
                'error' => $e->getMessage()
            ]);
            $results['errors']++;
        }

        return $results;
    }

    private function fetchSpeed(Customer $customer): ?string
    {
        $olt = $customer->olt;
        $gpon_interface = $customer->gpon_interface;
    
        if (!$olt || !$olt->ip_olt) {
            return null;
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
                'username' => 'gpon_itel',
                'password' => 'itel2025..',
                'enable_password' => 'string',
                'timeout' => 15,
                'retries' => 2,
                'connection_type' => 'telnet',
                'port' => 23
            ],
            'commands' => $commands
        ];
    
        try {
            $response = Http::timeout(60)->post(
                "http://x8okocwgko08ooswggoko4g4.172.16.255.5.sslip.io/execute",
                $payload
            );
    
            if (!$response->successful()) {
                return null;
            }
    
            $output = $response->json()['results'][0]['output'] ?? '';
    
            Log::info("Output completo de velocidad {$customer->id}", [
                'output' => $output
            ]);
    
            $speed = null;
    
            if (preg_match('/downstream\s+(\d+)\s*(Mbps|Gbps)/i', $output, $matches)) {
                $value = (float)$matches[1];
                $unit = $matches[2];
    
                if ($unit === 'Gbps') {
                    $value *= 1000;
                    $unit = 'Mbps';
                }
    
                $speed = "{$value}{$unit}";
            }
    
            Log::info("Velocidad a actualizar", [
                'speed' => $speed,
            ]);
    
            return $speed;
    
        } catch (\Exception $e) {
            Log::error("Error obteniendo velocidad de cliente {$customer->id}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }    
}
