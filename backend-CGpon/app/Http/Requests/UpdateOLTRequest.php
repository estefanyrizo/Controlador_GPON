<?php

namespace App\Http\Requests;

use App\Helpers\GeneralHelper;
use App\Models\OltModel;
use App\Models\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateOLTRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user_type = GeneralHelper::get_user_type_code();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'ip_olt' => ['required', 'ip'],
            'description' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'string', 'max:5'],
            'username' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'max:50'],
            'must_login' => ['nullable', 'in:yes,no'],
            'relation_name' => ['nullable', 'string', 'max:255'],
            'relation_notes' => ['nullable', 'string', 'max:1000'],
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del OLT es obligatorio',
            'ip_olt.required' => 'La dirección IP del OLT es obligatoria',
            'ip_olt.ip' => 'La dirección IP debe ser válida',
            // Connection Configuration fields - optional messages
            'port.string' => 'El puerto debe ser un texto válido',
            'port.max' => 'El puerto no puede tener más de 5 caracteres',
            'username.string' => 'El nombre de usuario debe ser un texto válido',
            'username.max' => 'El nombre de usuario no puede tener más de 50 caracteres',
            'password.string' => 'La contraseña debe ser un texto válido',
            'password.max' => 'La contraseña no puede tener más de 50 caracteres',
            'must_login.in' => 'El valor para requiere login debe ser si o no',
        ];
    }
}
