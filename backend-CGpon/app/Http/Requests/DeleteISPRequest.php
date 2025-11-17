<?php

namespace App\Http\Requests;

use App\Helpers\GeneralHelper;
use Illuminate\Foundation\Http\FormRequest;

class DeleteISPRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user_type = GeneralHelper::get_user_type_code();

        // Only superadmin and main_provider can delete ISPs
        return in_array($user_type, ['superadmin', 'main_provider']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // No specific validation rules needed for delete
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
            // No specific messages needed
        ];
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function failedAuthorization()
    {
        \Log::warning('Unauthorized ISP delete attempt', [
            'user_id' => auth()->id(),
            'user_type' => GeneralHelper::get_user_type_code(),
            'isp_id' => $this->route('isp')
        ]);

        parent::failedAuthorization();
    }
}
