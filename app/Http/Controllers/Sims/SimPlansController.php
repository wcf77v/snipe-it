<?php

namespace App\Http\Controllers\Sims;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSimPlanRequest;
use App\Models\Company;
use App\Models\CompanyableScope;
use App\Models\CustomFieldset;
use App\Models\Sim;
use App\Models\SimPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * UI for SIM plans (tariffs / SIM types). A plan carries the operator and
 * the custom fieldset for its SIMs, like an asset model does for assets.
 */
class SimPlansController extends Controller
{
    public function index(): View
    {
        $this->authorize('index', SimPlan::class);

        return view('sims/plans/index');
    }

    public function create(): View
    {
        $this->authorize('create', SimPlan::class);

        return view('sims/plans/edit')
            ->with('item', new SimPlan)
            ->with('fieldsets', CustomFieldset::orderBy('name')->pluck('name', 'id'));
    }

    public function store(SaveSimPlanRequest $request): RedirectResponse
    {
        $this->authorize('create', SimPlan::class);

        $plan = new SimPlan;
        $plan->fill($request->only(['name', 'supplier_id', 'fieldset_id', 'monthly_cost', 'notes']));
        $plan->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $plan->created_by = auth()->id();

        if ($plan->save()) {
            Sim::flushCustomFieldset();

            return redirect()->route('simplans.show', $plan)
                ->with('success', trans('admin/sims/message.plan.create_success'));
        }

        return redirect()->back()->withInput()->withErrors($plan->getErrors());
    }

    public function show(SimPlan $simplan): View
    {
        $this->authorize('view', $simplan);

        return view('sims/plans/view', ['plan' => $simplan]);
    }

    public function edit(SimPlan $simplan): View
    {
        $this->authorize('update', $simplan);

        return view('sims/plans/edit')
            ->with('item', $simplan)
            ->with('fieldsets', CustomFieldset::orderBy('name')->pluck('name', 'id'));
    }

    public function update(SaveSimPlanRequest $request, SimPlan $simplan): RedirectResponse
    {
        $this->authorize('update', $simplan);

        $simplan->fill($request->only(['name', 'supplier_id', 'fieldset_id', 'monthly_cost', 'notes']));
        $simplan->company_id = Company::getIdForCurrentUser($request->input('company_id'));

        if ($simplan->save()) {
            Sim::flushCustomFieldset();

            return redirect()->route('simplans.show', $simplan)
                ->with('success', trans('admin/sims/message.plan.update_success'));
        }

        return redirect()->back()->withInput()->withErrors($simplan->getErrors());
    }

    public function destroy(SimPlan $simplan): RedirectResponse
    {
        $this->authorize('delete', $simplan);

        if ($simplan->sims()->withoutGlobalScope(CompanyableScope::class)->count() > 0) {
            return redirect()->route('simplans.show', $simplan)
                ->with('error', trans('admin/sims/message.plan.has_sims'));
        }

        $simplan->delete();

        return redirect()->route('simplans.index')
            ->with('success', trans('admin/sims/message.plan.delete_success'));
    }
}
