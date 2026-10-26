<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Sim;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class SimsTransformer
{
    public function transformSims(Collection $sims, $total): array
    {
        $array = [];

        // Resolve the secrets gate once per page instead of once per row.
        $canViewSecrets = Gate::allows('viewSecrets', Sim::class);

        foreach ($sims as $sim) {
            $array[] = $this->transformSim($sim, $canViewSecrets);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    /**
     * @param  bool|null  $canViewSecrets  Precomputed viewSecrets result; when
     *                                     null it's checked against this SIM.
     */
    public function transformSim(Sim $sim, ?bool $canViewSecrets = null): array
    {
        $canViewSecrets ??= Gate::allows('viewSecrets', $sim);

        $array = [
            'id' => (int) $sim->id,
            'name' => e($sim->display_name),
            'sim_name' => ($sim->name) ? e($sim->name) : null,
            'iccid' => e($sim->iccid),
            'msisdn' => ($sim->msisdn) ? e($sim->msisdn) : null,
            'msisdn_formatted' => ($sim->msisdn) ? e($sim->formatted_msisdn) : null,
            'phone_country' => ($sim->phone_country) ? e($sim->phone_country) : null,
            'imsi' => ($sim->imsi) ? e($sim->imsi) : null,
            'sim_type' => e($sim->sim_type),
            'sim_type_label' => e(trans('admin/sims/general.type_'.$sim->sim_type)),
            'qr_code_url' => route('qr_code/common', ['object_type' => 'sims', 'id' => $sim->id]),
            'supplier' => ($sim->supplier) ? [
                'id' => (int) $sim->supplier->id,
                'name' => e($sim->supplier->name),
                'tag_color' => $sim->supplier->tag_color ? e($sim->supplier->tag_color) : null,
            ] : null,
            'sim_plan' => ($sim->simPlan) ? [
                'id' => (int) $sim->simPlan->id,
                'name' => e($sim->simPlan->name),
            ] : null,
            'account_number' => ($sim->account_number) ? e($sim->account_number) : null,
            'monthly_cost' => Helper::formatCurrencyOutput($sim->monthly_cost),
            'status' => ($sim->status) ? [
                'id' => (int) $sim->status->id,
                'name' => e($sim->status->name),
                'status_type' => e($sim->status->getStatuslabelType()),
                'status_meta' => e($sim->statusMeta()),
            ] : null,
            'company' => ($sim->company) ? [
                'id' => (int) $sim->company->id,
                'name' => e($sim->company->name),
                'tag_color' => $sim->company->tag_color ? e($sim->company->tag_color) : null,
            ] : null,
            'location' => ($sim->location) ? [
                'id' => (int) $sim->location->id,
                'name' => e($sim->location->name),
                'tag_color' => $sim->location->tag_color ? e($sim->location->tag_color) : null,
            ] : null,
            'department' => ($sim->department) ? [
                'id' => (int) $sim->department->id,
                'name' => e($sim->department->name),
            ] : null,
            'assigned_to' => $this->transformAssignedTo($sim),
            'last_checkout' => Helper::getFormattedDateObject($sim->last_checkout, 'datetime'),
            'last_checkin' => Helper::getFormattedDateObject($sim->last_checkin, 'datetime'),
            'expected_checkin' => Helper::getFormattedDateObject($sim->expected_checkin, 'date'),
            'activation_date' => Helper::getFormattedDateObject($sim->activation_date, 'date'),
            'expiration_date' => Helper::getFormattedDateObject($sim->expiration_date, 'date'),
            'notes' => ($sim->notes) ? Helper::parseEscapedMarkedownInline($sim->notes) : null,
            'created_by' => ($sim->adminuser) ? [
                'id' => (int) $sim->adminuser->id,
                'name' => e($sim->adminuser->display_name),
            ] : null,
            'created_at' => Helper::getFormattedDateObject($sim->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($sim->updated_at, 'datetime'),
            'deleted_at' => Helper::getFormattedDateObject($sim->deleted_at, 'datetime'),
            'user_can_checkout' => $sim->availableForCheckout(),
        ];

        // Secrets are omitted entirely (not just masked) for callers without
        // the permission, so their presence can't be inferred either.
        if ($canViewSecrets) {
            $array['pin'] = $sim->pin ? e($sim->pin) : null;
            $array['puk'] = $sim->puk ? e($sim->puk) : null;
            $array['activation_code'] = $sim->activation_code ? e($sim->activation_code) : null;
        }

        $array['custom_fields'] = $this->transformCustomFields($sim, $canViewSecrets);

        $array['available_actions'] = [
            'checkout' => ($sim->deleted_at == '') && Gate::allows('checkout', Sim::class),
            'checkin' => ($sim->deleted_at == '') && Gate::allows('checkin', Sim::class),
            'update' => ($sim->deleted_at == '') && Gate::allows('update', Sim::class),
            'clone' => Gate::allows('create', Sim::class),
            'delete' => ($sim->deleted_at == '') && (! $sim->assigned_to) && Gate::allows('delete', Sim::class),
            'restore' => ($sim->deleted_at != '') && Gate::allows('create', Sim::class),
        ];

        return $array;
    }

    /**
     * Same shape as AssetsTransformer's custom_fields so the shared
     * customFieldsFormatter in bootstrap-table works unchanged.
     */
    public function transformCustomFields(Sim $sim, bool $canViewSecrets): array|\stdClass
    {
        $fields = $sim->customFields();

        if ($fields->isEmpty()) {
            return new \stdClass;
        }

        $fieldsArray = [];

        foreach ($fields as $field) {
            if (($field->field_encrypted == '1') && ! $canViewSecrets) {
                $value = strtoupper(trans('admin/custom_fields/general.encrypted'));
            } else {
                $value = $sim->customFieldValue($field);

                if (in_array($field->format, ['DATE', 'DATETIME']) && ($value !== null) && ($value !== '')) {
                    $value = Helper::getFormattedDateObject($value, $field->format == 'DATETIME' ? 'datetime' : 'date', false);
                }
            }

            $fieldsArray[$field->name] = [
                'field' => e($field->db_column),
                'value' => ($field->element == 'markdown-textarea') ? Helper::renderMarkdown($value) : e($value),
                'field_format' => $field->format,
                'element' => $field->element,
            ];
        }

        return $fieldsArray;
    }

    /**
     * Mirrors AssetsTransformer::transformAssignedTo(), including the guard
     * that strips user PII from callers who can't view users.
     */
    public function transformAssignedTo(Sim $sim): ?array
    {
        // MorphTo is named `assigned` (see Sim::assignedTo()); after eager-load
        // the relation is stored under that name — same as Asset::$assigned.
        $target = $sim->assigned;

        if (! $target) {
            return null;
        }

        if ($target instanceof User) {
            if (Gate::denies('view', $target)) {
                return [
                    'id' => (int) $target->id,
                    'type' => 'user',
                    'name' => e($target->display_name),
                    'deleted_at' => Helper::getFormattedDateObject($target->deleted_at, 'datetime'),
                ];
            }

            return [
                'id' => (int) $target->id,
                'type' => 'user',
                'name' => e($target->display_name),
                'username' => e($target->username),
                'email' => ($target->email) ? e($target->email) : null,
                'employee_number' => ($target->employee_num) ? e($target->employee_num) : null,
                'deleted_at' => Helper::getFormattedDateObject($target->deleted_at, 'datetime'),
            ];
        }

        return [
            'id' => (int) $target->id,
            'type' => $sim->assignedType(),
            'name' => e($target->display_name),
            'deleted_at' => Helper::getFormattedDateObject($target->deleted_at, 'datetime'),
        ];
    }

    /**
     * Minimal user shape for phone-match results. PII beyond the display
     * name is only included for callers allowed to view the user.
     */
    public function transformUserCompact(User $user): array
    {
        $array = [
            'id' => (int) $user->id,
            'type' => 'user',
            'name' => e($user->display_name),
        ];

        if (Gate::allows('view', $user)) {
            $array['username'] = e($user->username);
            $array['phone'] = $user->phone ? e($user->phone) : null;
            $array['mobile'] = $user->mobile ? e($user->mobile) : null;
        }

        return $array;
    }

    /**
     * Compact shape used by select2 and by embedding into other objects.
     */
    public function transformSimCompact(Sim $sim): array
    {
        return [
            'id' => (int) $sim->id,
            'type' => 'sim',
            'name' => e($sim->display_name),
            'iccid' => e($sim->iccid),
            'msisdn' => ($sim->msisdn) ? e($sim->msisdn) : null,
        ];
    }
}
