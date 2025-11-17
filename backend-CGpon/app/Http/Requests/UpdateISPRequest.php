<?php

namespace App\Http\Requests;

use App\Helpers\GeneralHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateISPRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $userType = GeneralHelper::get_user_type_code();

        // Only superadmin and main provider can update ISPs
        return in_array($userType, ['superadmin', 'main_provider']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('isps')->ignore($this->route('isp'))
            ],
            'description' => 'nullable|string|max:1000',
            'status_id' => 'sometimes|exists:statuses,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del ISP es obligatorio.',
            'name.max' => 'El nombre del ISP no puede exceder los 255 caracteres.',
            'name.unique' => 'Este nombre de ISP ya está en uso.',
            'description.max' => 'La descripción no puede exceder los 1000 caracteres.',
            'status_id.exists' => 'El estado seleccionado no es válido.',
        ];
    }
}
