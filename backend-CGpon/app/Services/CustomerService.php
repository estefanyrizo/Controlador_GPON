<?php

namespace App\Services;

class CustomerService {

    /**
     * Summary of columns_fill_per_user_type
     * @param string $user_type
     * @return string[]
     */
    public function columns_fill_per_user_type (string $user_type, array $data): array {
        $columns = match ($user_type) {
            'superadmin' => ['olt_id', 'gpon_interface', 'service_number', 'code_customer', 'customer_name'],
            'main_provider' => ['gpon_interface'],
            'isp' => ['olt_id', 'service_number', 'code_customer', 'customer_name'],
            default => []
        };

        return array_intersect_key($data, array_flip($columns));
    }

    function validate_GPON_interface_format ($string) {
        // Dividir la cadena en partes utilizando los separadores esperados
        $partes = explode('/', $string);

        // Validar que tenga exactamente tres partes separadas por '/'
        if (count($partes) !== 3) {
            return false;
        }

        // Validar que la primera parte sea "0"
        if ($partes[0] !== '0') {
            return false;
        }

        // Validar que la segunda parte sea un número entre 1 y 2
        if (!is_numeric($partes[1]) || (int)$partes[1] < 1 || (int)$partes[1] > 2) {
            return false;
        }

        // Dividir la tercera parte por ':'
        $subPartes = explode(':', $partes[2]);

        // Validar que tenga exactamente dos subpartes
        if (count($subPartes) !== 2) {
            return false;
        }

        // Dividir la segunda subparte por '-'
        $finalPartes = explode('-', $subPartes[1]);

        // Validar que tenga exactamente dos partes
        if (count($finalPartes) !== 2) {
            return false;
        }

        // Validar que el tercer número (antes del ':') sea entre 0 y 15
        if (!is_numeric($subPartes[0]) || (int)$subPartes[0] < 0 || (int)$subPartes[0] > 15) {
            return false;
        }

        // Validar que el cuarto número (antes del '-') sea entre 0 y 127
        if (!is_numeric($finalPartes[0]) || (int)$finalPartes[0] < 0 || (int)$finalPartes[0] > 127) {
            return false;
        }

        // Validar que el quinto número (después del '-') sea entre 0 y 4096
        if (!is_numeric($finalPartes[1]) || (int)$finalPartes[1] < 0 || (int)$finalPartes[1] > 4096) {
            return false;
        }

        return true;
    }
}
