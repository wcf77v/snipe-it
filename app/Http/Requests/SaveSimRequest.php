<?php

namespace App\Http\Requests;

use App\Helpers\Helper;
use App\Models\Department;
use App\Models\Sim;
use App\Models\SimPlan;
use App\Services\PhoneNumberService;
use Illuminate\Support\Facades\Gate;

/**
 * Validates SIM create/update input from both the UI and the API.
 *
 * The Sim model validates itself again on save (watson/validating); this
 * layer adds the rules that can't live on the model: clear-text format of
 * the encrypted secrets and the custom field values from the SIM fieldset.
 */
class SaveSimRequest extends Request
{
    /**
     * Authorization is done in the controllers against the concrete SIM
     * (create vs. update, FMCS scoping).
     */
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->filled('iccid')) {
            $this->merge(['iccid' => strtoupper(preg_replace('/[\s\-]/', '', (string) $this->input('iccid')))]);
        }

        if ($this->filled('imsi')) {
            $this->merge(['imsi' => preg_replace('/\s/', '', (string) $this->input('imsi'))]);
        }

        // The operator defaults to the plan's, before "required" is checked.
        if (! $this->filled('supplier_id') && $this->filled('sim_plan_id')) {
            $planSupplier = SimPlan::whereKey((int) $this->input('sim_plan_id'))->value('supplier_id');
            if ($planSupplier && (! $this->isPartialUpdate() || $this->exists('supplier_id'))) {
                $this->merge(['supplier_id' => $planSupplier]);
            }
        }
    }

    public function rules(): array
    {
        $sim = ($this->route('sim') instanceof Sim) ? $this->route('sim') : null;

        $rules = [
            'name' => ['nullable', 'string', 'max:191'],
            'iccid' => ['nullable', 'string', 'regex:/^[0-9]{18,22}F?$/'],
            'phone_country' => ['nullable', 'string', 'size:2', function ($attribute, $value, $fail) {
                if (! PhoneNumberService::cleanCountry($value)) {
                    $fail(trans('admin/sims/message.invalid_country'));
                }
            }],
            // Any notation is accepted ("8 029 123-45-67", "+375 29 1234567")
            // as long as it is a valid number for the selected country.
            'msisdn' => ['nullable', 'string', 'max:32', function ($attribute, $value, $fail) {
                if (! PhoneNumberService::isValid($value, $this->input('phone_country'))) {
                    $fail(trans('admin/sims/message.invalid_phone', ['example' => PhoneNumberService::example($this->input('phone_country'))]));
                }
            }],
            'imsi' => ['nullable', 'string', 'regex:/^[0-9]{6,15}$/'],
            'sim_type' => ['nullable', 'in:'.implode(',', Sim::TYPES)],
            // Looked up through the model so FMCS company scoping applies:
            // a plan of another company must not be assignable by ID.
            'sim_plan_id' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if (! SimPlan::whereKey($value)->exists()) {
                    $fail(trans('validation.exists', ['attribute' => trans('admin/sims/table.plan')]));
                }
            }],
            'supplier_id' => ['nullable', 'integer', 'exists_undeleted:suppliers,id'],
            'account_number' => ['nullable', 'string', 'max:191'],
            'status_id' => ['nullable', 'integer', 'exists:status_labels,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'location_id' => ['nullable', 'integer', 'exists_undeleted:locations,id'],
            // FMCS-scoped lookup, like sim_plan_id.
            'department_id' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if (! Department::whereKey($value)->exists()) {
                    $fail(trans('validation.exists', ['attribute' => trans('general.department')]));
                }
            }],
            // Owner. Changing it checks the SIM in/out, so it needs those permissions.
            'assigned_user' => ['nullable', 'integer', 'exists_undeleted:users,id', function ($attribute, $value, $fail) use ($sim) {
                $alreadyAssigned = $sim && $sim->checkedOutToUser() && ((int) $sim->assigned_to === (int) $value);
                if (! $alreadyAssigned && Gate::denies('checkout', Sim::class)) {
                    $fail(trans('admin/sims/message.assign_not_allowed'));
                }
            }],
            'activation_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:activation_date'],
            'notes' => ['nullable', 'string', 'max:65535'],
            // Secrets are validated in clear text; the model encrypts them.
            'pin' => ['nullable', 'string', 'regex:/^[0-9]{4,8}$/'],
            'puk' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            'activation_code' => ['nullable', 'string', 'max:1024'],
        ];

        // Fields the admin made required in the SIM settings.
        foreach (Sim::requiredFields() as $field) {
            // A SIM already checked out to an asset/location has an owner even
            // though the user dropdown is empty.
            if (($field === 'assigned_user') && $sim && $sim->assigned_to && ! $sim->checkedOutToUser()) {
                continue;
            }
            $rules[$field] = array_map(fn ($rule) => ($rule === 'nullable') ? 'required' : $rule, $rules[$field]);
        }

        $partial = $this->isPartialUpdate();

        if ($partial) {
            foreach ($rules as $field => $fieldRules) {
                array_unshift($rules[$field], 'sometimes');
            }
        }

        return $rules + Sim::customFieldRequestRules($this->targetPlanId(), $partial);
    }

    public function attributes(): array
    {
        $attributes = [
            'iccid' => trans('admin/sims/table.iccid'),
            'msisdn' => trans('admin/sims/table.msisdn'),
            'phone_country' => trans('admin/sims/table.phone_country'),
            'imsi' => trans('admin/sims/table.imsi'),
            'pin' => trans('admin/sims/table.pin'),
            'puk' => trans('admin/sims/table.puk'),
            'activation_code' => trans('admin/sims/table.activation_code'),
            'supplier_id' => trans('admin/sims/table.operator'),
            'sim_plan_id' => trans('admin/sims/table.plan'),
            'assigned_user' => trans('general.user'),
            'department_id' => trans('general.department'),
            'location_id' => trans('general.location'),
            'account_number' => trans('admin/sims/table.account_number'),
            'activation_date' => trans('admin/sims/table.activation_date'),
            'expiration_date' => trans('admin/sims/table.expiration_date'),
        ];

        foreach (Sim::fieldsetForPlan($this->targetPlanId())?->fields ?? [] as $field) {
            $attributes[$field->db_column] = $field->name;
        }

        return $attributes;
    }

    /**
     * The plan the SIM will be on after this request: the submitted one,
     * or (for partial API updates that don't send it) the current one.
     */
    public function targetPlanId(): ?int
    {
        if ($this->exists('sim_plan_id') || ! $this->isPartialUpdate()) {
            return $this->input('sim_plan_id') ? (int) $this->input('sim_plan_id') : null;
        }

        $sim = $this->route('sim');

        return ($sim instanceof Sim) ? $sim->sim_plan_id : null;
    }

    /**
     * API PUT/PATCH only touch the fields that were sent.
     */
    public function isPartialUpdate(): bool
    {
        return $this->is('api/*') && in_array($this->method(), ['PUT', 'PATCH'], true);
    }

    public function response(array $errors)
    {
        if ($this->ajax() || $this->wantsJson()) {
            return Helper::formatStandardApiResponse('error', null, $errors);
        }

        return $this->redirector->back()->withInput()->withErrors($errors, $this->errorBag);
    }
}
