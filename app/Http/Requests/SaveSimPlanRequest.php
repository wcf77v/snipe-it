<?php

namespace App\Http\Requests;

use App\Helpers\Helper;

class SaveSimPlanRequest extends Request
{
    protected $rules = [
        'name' => 'required|string|max:191',
        'supplier_id' => 'nullable|integer|exists_undeleted:suppliers,id',
        'fieldset_id' => 'nullable|integer|exists:custom_fieldsets,id',
        'company_id' => 'nullable|integer|exists:companies,id',
        'monthly_cost' => 'nullable|numeric|gte:0|max:99999999999999999.99',
        'notes' => 'nullable|string|max:65535',
    ];

    /**
     * Authorization is done in the controllers (create vs. update).
     */
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->filled('monthly_cost') && ! is_float($this->input('monthly_cost')) && preg_match('/^[\d.,\s]+$/', (string) $this->input('monthly_cost'))) {
            $this->merge(['monthly_cost' => Helper::ParseCurrency($this->input('monthly_cost'))]);
        }
    }
}
