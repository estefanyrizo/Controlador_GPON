<?php

namespace App\Http\Requests;

use App\Helpers\GeneralHelper;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allow update based on user type
        $user_type = GeneralHelper::get_user_type_code();
        return in_array($user_type, ['superadmin', 'main_provider', 'isp_representative']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user_type = GeneralHelper::get_user_type_code();
        $customerId = $this->route('customer')->id;

        // Base rules that are common for all user types
        $rules = [
            'comment' => 'nullable|string|max:500',
        ];

        // Add additional rules based on user type
        if ($user_type === 'superadmin') {
            $rules = array_merge($rules, [
                'gpon_interface' => 'nullable|string|max:50' . $customerId,
                'service_number' => 'required|string|max:50|unique:customers,service_number,' . $customerId,
                'code_customer' => 'required|string|max:50|unique:customers,code_customer,' . $customerId,
                'customer_name' => 'required|string|max:255',
                'olt_id' => 'nullable|exists:olts,id',
            ]);
        } elseif ($user_type === 'main_provider') {
            $rules = array_merge($rules, [
                'gpon_interface' => 'nullable|string|max:50' . $customerId,
                'service_number' => 'required|string|max:50|unique:customers,service_number,' . $customerId,
                'code_customer' => 'required|string|max:50|unique:customers,code_customer,' . $customerId,
                'customer_name' => 'required|string|max:255',
                'olt_id' => 'nullable|exists:olts,id',
            ]);
        } elseif ($user_type === 'isp_representative') {
            $rules = array_merge($rules, [
                'service_number' => 'required|string|max:50|unique:customers,service_number,' . $customerId,
                'code_customer' => 'required|string|max:50|unique:customers,code_customer,' . $customerId,
                'customer_name' => 'required|string|max:255',
                'olt_id' => 'nullable|exists:olts,id',
            ]);
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If OLT is unselected (null), automatically clear the GPON interface
        if ($this->input('olt_id') === null) {
            $this->merge([
                'gpon_interface' => null,
            ]);
        }
    }
}
