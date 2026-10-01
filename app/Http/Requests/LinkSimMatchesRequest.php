<?php

namespace App\Http\Requests;

use App\Models\Setting;

class LinkSimMatchesRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'matches' => 'required|array|min:1|max:500',
            'matches.*' => ['string', 'regex:/^[0-9]+:[0-9]+$/'],
            'note' => 'nullable|string|max:65535',
        ];

        if (Setting::getSettings()->require_checkinout_notes) {
            $rules['note'] = 'required|string|max:65535';
        }

        return $rules;
    }
}
