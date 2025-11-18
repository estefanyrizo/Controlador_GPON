<?php

namespace App\Http\Requests;

use App\Helpers\GeneralHelper;
use App\Models\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Database\Query\Builder;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user_type_code = GeneralHelper::get_user_type_code();
        $allowed_types = ['superadmin', 'main_provider'];
        if (in_array($user_type_code, $allowed_types)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $entered_user_type_id = $this->input('user_type_id');
        $isp_representative_user_type_id = UserType::where('code', 'isp_representative')->value('id');
        $is_isp_id_required = $entered_user_type_id == $isp_representative_user_type_id;

        $user_type_code = GeneralHelper::get_user_type_code();
        $user = $this->route('user');

        $users_unique_on_active_generic = Rule::unique('users')
        ->where(function ($query) {
            $query->where('status', true); // solo usuarios activos
        })
        ->ignore($user->id);
    
        // Base rules that apply to all user types
        $rules = [
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', $users_unique_on_active_generic],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', $users_unique_on_active_generic],
            'password' => ['nullable', Rules\Password::defaults()],
        ];

        // Add ISP ID validation only if the user type is ISP representative
        if ($is_isp_id_required) {
            $rules['isp_id'] = ['required', Rule::exists('isps', 'id')];
        } else {
            $rules['isp_id'] = ['nullable'];
        }

        // Add user type rules based on current user's privileges
        if ($user_type_code == 'superadmin') {
            $rules['user_type_id'] = ['required', Rule::exists('user_types', 'id')];
        } else if ($user_type_code == 'main_provider') {
            $rules['user_type_id'] = [
                'required',
                Rule::exists('user_types', 'id')->where(function (Builder $query) {
                    $query->whereNot('user_type_code', 'superadmin'); // Solo los superadmins pueden crear otros superadmins
                })
            ];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',

            'username.required' => 'El nombre de usuario es obligatorio.',
            'username.string' => 'El nombre de usuario debe ser una cadena de texto.',
            'username.max' => 'El nombre de usuario no puede tener más de 255 caracteres.',
            'username.unique' => 'El nombre de usuario ya está en uso.',

            'email.string' => 'El correo electrónico debe ser una cadena de texto.',
            'email.lowercase' => 'El correo electrónico debe estar en minúsculas.',
            'email.email' => 'El correo electrónico debe ser una dirección válida.',
            'email.max' => 'El correo electrónico no puede tener más de 255 caracteres.',
            'email.unique' => 'El correo electrónico ya está en uso.',

            'user_type_id.required' => 'El tipo de usuario es obligatorio.',
            'user_type_id.exists' => 'El tipo de usuario seleccionado no es válido.',

            'isp_id.required_if' => 'El ID del ISP es obligatorio para representantes de ISP.',
            'isp_id.exists' => 'El ID del ISP seleccionado no es válido.',

            'status_id.required' => 'El estado es obligatorio.',
            'status_id.exists' => 'El estado seleccionado no es válido.',
        ];
    }
}
