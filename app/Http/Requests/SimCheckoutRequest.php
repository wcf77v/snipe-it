<?php

namespace App\Http\Requests;

use App\Models\Setting;

class SimCheckoutRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // exists_undeleted keeps SIMs from being bound to trashed targets,
        // same as AssetCheckoutRequest.
        $rules = [
            'checkout_to_type' => 'required|in:asset,location,user',
            'assigned_user' => 'numeric|nullable|required_if:checkout_to_type,user|exists_undeleted:users,id',
            'assigned_asset' => 'numeric|nullable|required_if:checkout_to_type,asset|exists_undeleted:assets,id',
            'assigned_location' => 'numeric|nullable|required_if:checkout_to_type,location|exists_undeleted:locations,id',
            'checkout_at' => 'nullable|date',
            // Same as asset checkout: only deployable labels.
            'status_id' => 'nullable|integer|exists:status_labels,id,deployable,1',
            'expected_checkin' => 'nullable|date',
            'note' => 'nullable|string|max:65535',
        ];

        if (Setting::getSettings()->require_checkinout_notes) {
            $rules['note'] = 'required|string|max:65535';
        }

        return $rules;
    }

    public function response(array $errors)
    {
        return $this->redirector->back()->withInput()->withErrors($errors, $this->errorBag);
    }
}
