<?php

namespace App\Http\Requests;

use App\Models\Sim;
use App\Services\PhoneNumberService;
use App\Services\SimPhoneMatcher;
use Illuminate\Support\Facades\Gate;

class StoreSimSettingsRequest extends Request
{
    public function authorize(): bool
    {
        return Gate::allows('manageSettings', Sim::class);
    }

    public function rules(): array
    {
        return [
            'sim_fieldset_id' => 'nullable|integer|exists:custom_fieldsets,id',
            'sim_default_country' => ['nullable', 'string', 'size:2', function ($attribute, $value, $fail) {
                if (! PhoneNumberService::cleanCountry($value)) {
                    $fail(trans('admin/sims/message.invalid_country'));
                }
            }],
            'sim_match_fields' => 'nullable|array',
            'sim_match_fields.*' => 'in:'.implode(',', array_keys(SimPhoneMatcher::FIELDS)),
            'sim_checkout_email' => 'nullable|boolean',
            'sim_required_fields' => 'nullable|array',
            'sim_required_fields.*' => 'in:'.implode(',', Sim::REQUIRABLE_FIELDS),
            'sim_profile_writeback' => 'required|in:'.implode(',', [
                SimPhoneMatcher::WRITEBACK_OFF,
                SimPhoneMatcher::WRITEBACK_EMPTY,
                SimPhoneMatcher::WRITEBACK_ALWAYS,
            ]),
        ];
    }
}
