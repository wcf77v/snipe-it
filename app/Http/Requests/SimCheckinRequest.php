<?php

namespace App\Http\Requests;

use App\Models\Setting;

class SimCheckinRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'checkin_at' => 'nullable|date',
            'status_id' => 'nullable|integer|exists:status_labels,id',
            'location_id' => 'nullable|integer|exists_undeleted:locations,id',
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
