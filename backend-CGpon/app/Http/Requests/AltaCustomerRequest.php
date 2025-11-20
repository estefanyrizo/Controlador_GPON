<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AltaCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // --- Campos que SÍ existen en BD ---
            'code_customer'   => 'required|string|max:255',
            'service_number'  => 'required|string|max:255',
            'customer_name'   => 'required|string|max:255',
            'olt_id'          => 'required|integer',
            'isp_id'          => 'required|integer',
            'gpon_interface'  => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'code_customer.required'  => 'El código de cliente es obligatorio.',
            'service_number.required' => 'El número de servicio es obligatorio.',
            'customer_name.required'  => 'El nombre del cliente es obligatorio.',
            'olt_id.required'         => 'Debe seleccionar una OLT.',
            'isp_id.required'         => 'Debe seleccionar un ISP.',
            'gpon_interface.required' => 'La interfaz GPON es obligatoria.',
        ];
    }

    public function validated($key = null, $default = null)
    {
        // Devuelve TODO lo validado, incluyendo los campos de lógica 
        // (serial, model, interface...) aunque no estén en la BD.
        return parent::validated($key, $default);
    }
}
