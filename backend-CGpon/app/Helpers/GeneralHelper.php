<?php
namespace App\Helpers;

use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class GeneralHelper {
    // public static function error_message ($exception) {
    //     $errorDetails = sprintf(
    //         "Error: %s\nFile: %s\nLine: %s\nStack Trace: %s",
    //         $exception->getMessage(),
    //         $exception->getFile(),
    //         $exception->getLine()
    //     );

    //     return [
    //         'message' => $errorDetails,
    //         'trace' => "Stack Trace:\n" . $exception->getTraceAsString()
    //     ];
    // }

    public static function get_user_type_code () {
        $user_info = UserType::join('users', 'users.user_type_id', '=', 'user_types.id')
            ->where('users.id', auth()->id())
            ->select('code')
            ->first();
        return $user_info?->code;
    }

    public static function checkIdenticalRowsAndValidate($table, $columnsWithValues)
    {
        $existingRows = DB::table($table)->where($columnsWithValues)->count();

        // If identical rows exist, add a custom error message
        if ($existingRows > 0) {
            $validator = Validator::make([], []); // Create a blank validator
            $validator->errors()->add('general_modal_form', 'Ya hay un registro con exactamente los mismos valores en la base de datos.');

            return redirect()->back()
                ->withErrors($validator); // Redirect back with error message
        }
    }

    public static function parseSpeedFromConfig(string $configOutput)
    {
        try {
            $lines = explode("\n", $configOutput);
            $speedLines = [];
            $customerName = '';
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'name ') !== false && !$customerName) {
                    $parts = explode('name ', $line, 2);
                    if (count($parts) > 1) {
                        $customerName = trim($parts[1]);
                    }
                }
                if (strpos($line, 'traffic-limit') !== false) {
                    if (preg_match('/gemport\s+(\d+).*traffic-limit\s+upstream\s+(\S+)\s+downstream\s+(\S+)/', $line, $matches)) {
                        $gemport = $matches[1];
                        $upstream = $matches[2];
                        $downstream = $matches[3];
                        $speedLines[] = [
                            'gemport' => $gemport,
                            'upstream' => $upstream,
                            'downstream' => $downstream,
                            'raw' => $line
                        ];
                    } else {
                        $speedLines[] = ['raw' => $line];
                    }
                }
            }
            
            if (!empty($speedLines)) {
                $formattedSpeed = "";
                if ($customerName) {
                    $formattedSpeed .= "Cliente: $customerName\n\n";
                }
                $formattedSpeed .= "Configuración de Velocidad:\n";
                $formattedSpeed .= str_repeat("=", 50) . "\n\n";
                
                foreach ($speedLines as $speedData) {
                    if (isset($speedData['gemport'])) {
                        $formattedSpeed .= "Gemport {$speedData['gemport']}:\n";
                        $formattedSpeed .= "  • Subida: {$speedData['upstream']}\n";
                        $formattedSpeed .= "  • Bajada: {$speedData['downstream']}\n\n";
                    } else {
                        $formattedSpeed .= "• {$speedData['raw']}\n\n";
                    }
                }
                $formattedSpeed .= str_repeat("-", 50) . "\n";
                $formattedSpeed .= "Configuración completa:\n\n";
                $formattedSpeed .= $configOutput;
                
                return trim($formattedSpeed);
            }
            
            return "No se encontró información de velocidad en la configuración.\n\nConfiguración recibida:\n" . $configOutput;
            
        } catch (\Exception $e) {
            Log::error('Error parsing speed from config', [
                'error' => $e->getMessage(),
                'config_output' => $configOutput
            ]);
            
            return "Error al procesar la información de velocidad.\n\nConfiguración recibida:\n" . $configOutput;
        }
    }

}
