<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSimPlanRequest;
use App\Http\Transformers\SelectlistTransformer;
use App\Http\Transformers\SimPlansTransformer;
use App\Models\Company;
use App\Models\CompanyableScope;
use App\Models\Sim;
use App\Models\SimPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimPlansController extends Controller
{
    public function index(Request $request): JsonResponse|array
    {
        $this->authorize('index', SimPlan::class);

        $allowed_columns = ['id', 'name', 'monthly_cost', 'sims_count', 'notes', 'created_at'];

        $plans = SimPlan::select('sim_plans.*')
            ->with('supplier', 'fieldset', 'company')
            ->withCount('sims as sims_count');

        if ($request->filled('filter') || $request->filled('search')) {
            $plans->TextSearch($request->input('filter') ? $request->input('filter') : $request->input('search'));
        }

        if ($request->filled('supplier_id')) {
            $plans->where('sim_plans.supplier_id', (int) $request->input('supplier_id'));
        }

        $total = $plans->count();
        $offset = ($request->input('offset') > $total) ? $total : app('api_offset_value');
        $limit = app('api_limit_value');
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'name';

        $plans = $plans->orderBy($sort === 'sims_count' ? 'sims_count' : 'sim_plans.'.$sort, $order)
            ->skip($offset)->take($limit)->get();

        return (new SimPlansTransformer)->transformSimPlans($plans, $total);
    }

    public function show(SimPlan $simplan): JsonResponse|array
    {
        $this->authorize('view', $simplan);

        return (new SimPlansTransformer)->transformSimPlan($simplan);
    }

    public function store(SaveSimPlanRequest $request): JsonResponse
    {
        $this->authorize('create', SimPlan::class);

        $plan = new SimPlan;
        $plan->fill($request->only(['name', 'supplier_id', 'fieldset_id', 'monthly_cost', 'notes']));
        $plan->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $plan->created_by = auth()->id();

        if ($plan->save()) {
            Sim::flushCustomFieldset();

            return response()->json(Helper::formatStandardApiResponse('success', (new SimPlansTransformer)->transformSimPlan($plan), trans('admin/sims/message.plan.create_success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $plan->getErrors()));
    }

    public function update(SaveSimPlanRequest $request, SimPlan $simplan): JsonResponse
    {
        $this->authorize('update', $simplan);

        $simplan->fill($request->only(['name', 'supplier_id', 'fieldset_id', 'monthly_cost', 'notes']));
        if ($request->exists('company_id')) {
            $simplan->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        }

        if ($simplan->save()) {
            Sim::flushCustomFieldset();

            return response()->json(Helper::formatStandardApiResponse('success', (new SimPlansTransformer)->transformSimPlan($simplan), trans('admin/sims/message.plan.update_success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $simplan->getErrors()));
    }

    public function destroy(SimPlan $simplan): JsonResponse
    {
        $this->authorize('delete', $simplan);

        if ($simplan->sims()->withoutGlobalScope(CompanyableScope::class)->count() > 0) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/sims/message.plan.has_sims')));
        }

        $simplan->delete();

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/sims/message.plan.delete_success')));
    }

    public function selectlist(Request $request): array
    {
        $this->authorize('view.selectlists');

        $plans = SimPlan::select(['sim_plans.id', 'sim_plans.name']);

        if ($request->filled('search') && is_string($request->input('search'))) {
            $plans->where('sim_plans.name', 'LIKE', '%'.$request->input('search').'%');
        }

        $plans = $plans->orderBy('name', 'ASC')->paginate(50);

        foreach ($plans as $plan) {
            $plan->use_text = e($plan->name);
        }

        return (new SelectlistTransformer)->transformSelectlist($plans);
    }
}
