<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\SimPlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class SimPlansTransformer
{
    public function transformSimPlans(Collection $plans, $total): array
    {
        $array = [];

        foreach ($plans as $plan) {
            $array[] = $this->transformSimPlan($plan);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformSimPlan(SimPlan $plan): array
    {
        return [
            'id' => (int) $plan->id,
            'name' => e($plan->name),
            'supplier' => ($plan->supplier) ? [
                'id' => (int) $plan->supplier->id,
                'name' => e($plan->supplier->name),
            ] : null,
            'fieldset' => ($plan->fieldset) ? [
                'id' => (int) $plan->fieldset->id,
                'name' => e($plan->fieldset->name),
            ] : null,
            'company' => ($plan->company) ? [
                'id' => (int) $plan->company->id,
                'name' => e($plan->company->name),
            ] : null,
            'monthly_cost' => Helper::formatCurrencyOutput($plan->monthly_cost),
            'sims_count' => (int) ($plan->sims_count ?? $plan->sims()->count()),
            'notes' => ($plan->notes) ? Helper::parseEscapedMarkedownInline($plan->notes) : null,
            'created_at' => Helper::getFormattedDateObject($plan->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($plan->updated_at, 'datetime'),
            'available_actions' => [
                'update' => ($plan->deleted_at == '') && Gate::allows('update', SimPlan::class),
                'delete' => $plan->isDeletable(),
            ],
        ];
    }
}
