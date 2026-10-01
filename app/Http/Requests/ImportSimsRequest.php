<?php

namespace App\Http\Requests;

use App\Models\Sim;
use Illuminate\Support\Facades\Gate;

class ImportSimsRequest extends Request
{
    protected $rules = [
        'file' => 'required|file|mimes:csv,txt|max:5120',
        'mode' => 'required|in:check,import',
        'create_missing' => 'nullable|boolean',
    ];

    public function authorize(): bool
    {
        return Gate::any(['create', 'update'], Sim::class);
    }
}
