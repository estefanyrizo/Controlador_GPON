<?php

namespace App\Http\Requests;

use App\Helpers\GeneralHelper;
use App\Models\OLT;
use App\Models\Status;
use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\Query\Builder;

class StoreCustomerRequest extends FormRequest
{
    public function __construct (protected CustomerService $customerService) {
        parent::__construct();
    }

    private function validateUniquenessOnISP($isp_id)
    {
        if (!$isp_id) {
            return function ($attribute, $value, $fail) {
                // If no ISP ID, this rule cannot be applied effectively.
                // Potentially allow or skip, or require ISP context.
            };
        }
        return Rule::unique('customers')->where(function ($query) use ($isp_id) {
            $query->whereIn('olt_id', function ($subQuery) use ($isp_id) {
                $subQuery->select('olts.id')
                    ->from('olts')
                    ->join('isp_olt', 'olts.id', '=', 'isp_olt.olt_id')
                    ->where('isp_olt.isp_id', $isp_id);
            });
        });
    }

    private function validateUniquenessOnOLT($olt_id)
    {
        if (!$olt_id) {
            return function ($attribute, $value, $fail) {
                // If no OLT ID, this rule cannot be applied.
            };
        }
        return Rule::unique('customers')->where('olt_id', $olt_id);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user_type_code = GeneralHelper::get_user_type_code();
        $allowed_types = ['superadmin', 'isp_representative'];
        return in_array($user_type_code, $allowed_types);
    }

    // This runs before the validation
    public function prepareForValidation() {
        GeneralHelper::checkIdenticalRowsAndValidate('customers', [
            'gpon_interface' => request()->input('gpon_interface'),
            'service_number' => request()->input('service_number'),
            'customer_name' => request()->input('customer_name'),
            'code_customer' => request()->input('code_customer'),
            'olt_id' => request()->input('olt_id'),
        ]);

        if (!$this->input('gpon_interface') && $this->input('olt_id')) {
            $olt = OLT::with('model')->find($this->input('olt_id'));
            if ($olt && $olt->model && $olt->model->gpon_interface_structure) {
                $structure = $olt->model->gpon_interface_structure;
                if (isset($structure['segments']) && isset($structure['full_string_pattern'])) {
                    $segmentValues = [];
                    $allPartsPresent = true;
                    foreach ($structure['segments'] as $index => $segment) {
                        $partValue = $this->input('gpon_interface_part' . ($index + 1));
                        if ($partValue !== null) {
                            $segmentValues[] = $partValue;
                        } else {
                            $allPartsPresent = false;
                            break;
                        }
                    }
                    if ($allPartsPresent && !empty($segmentValues)) {
                        try {
                            $fullGponInterface = vsprintf($structure['full_string_pattern'], $segmentValues);
                            $this->merge(['gpon_interface' => $fullGponInterface]);
                        } catch (\ValueError $e) {
                            // Handle error if vsprintf fails (e.g. wrong number of arguments)
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $activeStatusId = Status::where('code', 'active')->value('id');
        $user_type_code = GeneralHelper::get_user_type_code();

        $requestedOltId = $this->input('olt_id');
        $olt = null;
        $gponInterfaceRules = ['nullable', 'string', 'max:255'];

        if ($requestedOltId) {
            $olt = OLT::with('model')->find($requestedOltId);
            if ($olt && $olt->model && !empty($olt->model->gpon_interface_structure)) {
                $structure = $olt->model->gpon_interface_structure;
                if (isset($structure['validation_regex']) && !empty($structure['validation_regex'])) {
                    // Ensure the regex pattern has proper delimiters and Unicode modifier
                    $pattern = trim($structure['validation_regex']);
                    // Remove any existing delimiters and modifiers if present
                    $pattern = trim($pattern, '/^$');

                    // Only escape forward slashes since we're using / as the delimiter
                    $pattern = str_replace('/', '\/', $pattern);

                    // Add proper delimiters and Unicode modifier
                    $theRegexRule = 'regex:/^' . $pattern . '$/u';

                    // Test the pattern directly before using it in validation rules
                    $input = $this->input('gpon_interface');
                    $testPattern = '/^' . $pattern . '$/u';
                    $isValid = @preg_match($testPattern, $input);

                    \Log::debug('GPON Validation Details', [
                        'input' => $input,
                        'pattern' => $pattern,
                        'full_pattern' => $testPattern,
                        'is_valid' => $isValid,
                        'preg_last_error' => preg_last_error(),
                        'preg_last_error_msg' => preg_last_error_msg()
                    ]);

                    // If pattern is invalid, fall back to a simpler validation
                    if ($isValid === false) {
                        \Log::warning('Invalid regex pattern detected, falling back to simple validation', [
                            'pattern' => $pattern,
                            'error' => preg_last_error_msg()
                        ]);
                        // Simple pattern that matches the format X/X/X:X where X are numbers
                        $theRegexRule = 'regex:/^\d+\/\d+\/\d+:\d+$/';
                    }

                    $gponInterfaceRules = ['required', 'string', 'max:255', $theRegexRule];
                } else {
                    // dd("No validation_regex found or it's empty in the structure for OLT model ID: " . ($olt->model->id ?? 'N/A'));
                    $gponInterfaceRules = ['required', 'string', 'max:255'];
                }
            } else {
                // dd("OLT, OLT model, or GPON structure not found or empty for OLT ID: " . $requestedOltId);
            }
        }

        $gponInterfaceRules[] = $this->validateUniquenessOnOLT($requestedOltId);

        $ispIdForUniqueness = null;
        if ($user_type_code === 'isp_representative') {
            $ispIdForUniqueness = $this->user()->isp_id;
        } elseif ($this->input('isp_id')) {
             $ispIdForUniqueness = $this->input('isp_id');
        }

        $commonRules = [
            'gpon_interface_part1' => ['nullable', 'string', 'max:50'],
            'gpon_interface_part2' => ['nullable', 'string', 'max:50'],
            'gpon_interface_part3' => ['nullable', 'string', 'max:50'],
            'gpon_interface_part4' => ['nullable', 'string', 'max:50'],

            'service_number' => ['nullable', 'string', 'digits_between:1,50', $this->validateUniquenessOnISP($ispIdForUniqueness)],
            'code_customer' => [
                'nullable',
                'string',
                'max:255',
                $this->validateUniquenessOnISP($ispIdForUniqueness)
            ],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'olt_id' => ['required', Rule::exists('olts', 'id')->where('status_id', $activeStatusId)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];

        $rules = ['gpon_interface' => $gponInterfaceRules] + $commonRules;

        if ($user_type_code === 'superadmin') {
            $rules['isp_id'] = ['nullable', Rule::exists('isps', 'id')->where('status_id', $activeStatusId)];
        } else if ($user_type_code === 'isp_representative') {
            $rules['olt_id'] = [
                'required',
                Rule::exists('olts', 'id')->where('status_id', $activeStatusId)
                    ->where(function (Builder $query) {
                        $query->whereHas('isps', function (Builder $subQuery) {
                            $subQuery->where('isps.id', $this->user()->isp_id);
                        });
                    })
            ];
        }

        // TEMPORARY DEBUG: Dump all rules before returning
        // Make sure to remove this dd() after debugging!
        // dd($rules);

        return $rules;
    }

    // This function checks that at least one field is not empty before creating the db record
    protected function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->all();
            unset($data['comment'], $data['_token'], $data['gpon_interface_part1'], $data['gpon_interface_part2'], $data['gpon_interface_part3'], $data['gpon_interface_part4']);

            $relevantFields = ['gpon_interface', 'service_number', 'code_customer', 'customer_name'];
            $filledRelevantFields = 0;
            foreach($relevantFields as $field) {
                if (!empty($data[$field])) {
                    $filledRelevantFields++;
                    break;
                }
            }

            if ($filledRelevantFields === 0 && empty($data['olt_id'])) {
                 $validator->errors()->add('general_modal_form', 'Debe completar al menos un campo del formulario (además de la OLT) para crear el registro.');
            }
        });
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'gpon_interface.regex' => 'El formato de la interfaz GPON no es válido para el modelo de OLT seleccionado.',
            'gpon_interface.required' => 'La interfaz GPON es requerida.',
            'gpon_interface.string' => 'La interfaz GPON debe ser una cadena de texto.',
            'gpon_interface.max' => 'La interfaz GPON no puede tener más de :max caracteres.',
        ];
    }
}
