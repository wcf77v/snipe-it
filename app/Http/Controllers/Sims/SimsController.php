<?php

namespace App\Http\Controllers\Sims;

use App\Actions\Sims\SaveSimAction;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSimRequest;
use App\Models\Sim;
use App\Models\User;
use App\Services\SimPhoneMatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * This class controls all UI actions related to SIM cards.
 */
class SimsController extends Controller
{
    /**
     * Listing page. The table itself is loaded from Api\SimsController::index().
     */
    public function index(): View
    {
        $this->authorize('index', Sim::class);

        return view('sims/index');
    }

    public function create(): View
    {
        $this->authorize('create', Sim::class);

        return view('sims/edit')
            ->with('item', new Sim(['sim_type' => Sim::TYPE_PHYSICAL]));
    }

    public function store(SaveSimRequest $request): RedirectResponse
    {
        $this->authorize('create', Sim::class);

        $sim = new Sim;

        session()->put(['redirect_option' => $request->input('redirect_option') === 'back' ? 'index' : $request->input('redirect_option')]);

        if (SaveSimAction::run($sim, $request)) {
            return Helper::getRedirectOption($request, $sim->id, 'Sims')
                ->with('success', trans('admin/sims/message.create.success'));
        }

        return redirect()->back()->withInput($request->except(Sim::SECRET_FIELDS))->withErrors($sim->getErrors());
    }

    public function show(Sim $sim): View
    {
        $this->authorize('view', $sim);

        // Users whose AD phone/mobile is this SIM's number, other than the
        // user it is already checked out to.
        // Who owns a phone number is user PII: only shown to people who may
        // view users.
        $phoneMatches = Gate::allows('view', User::class)
            ? SimPhoneMatcher::usersForSim($sim)
                ->reject(fn ($user) => $sim->checkedOutToUser() && ((int) $sim->assigned_to === (int) $user->id))
                ->values()
            : collect();

        return view('sims/view', ['sim' => $sim, 'phoneMatches' => $phoneMatches]);
    }

    public function edit(Sim $sim): View
    {
        $this->authorize('update', $sim);

        if ($safeReferer = Helper::sameOriginUrl(url()->previous())) {
            session()->put('url.intended', $safeReferer);
        }

        return view('sims/edit')
            ->with('item', $sim);
    }

    public function update(SaveSimRequest $request, Sim $sim): RedirectResponse
    {
        $this->authorize('update', $sim);

        session()->put(['redirect_option' => $request->input('redirect_option')]);

        if (SaveSimAction::run($sim, $request)) {
            return Helper::getRedirectOption($request, $sim->id, 'Sims')
                ->with('success', trans('admin/sims/message.update.success'));
        }

        return redirect()->back()->withInput($request->except(Sim::SECRET_FIELDS))->withErrors($sim->getErrors());
    }

    public function destroy(Sim $sim): RedirectResponse
    {
        $this->authorize('delete', $sim);

        if ($sim->assigned_to) {
            return redirect()->route('sims.show', $sim)
                ->with('error', trans('admin/sims/message.delete.assigned'));
        }

        $sim->delete();

        return redirect()->route('sims.index')
            ->with('success', trans('admin/sims/message.delete.success'));
    }

    public function restore(int $simId): RedirectResponse
    {
        $sim = Sim::onlyTrashed()->findOrFail($simId);
        $this->authorize('create', Sim::class);
        $this->authorize('view', $sim);

        // The ICCID may have been reused by a new card in the meantime.
        if (Sim::where('iccid', $sim->iccid)->exists()) {
            return redirect()->route('sims.index')
                ->with('error', trans('admin/sims/message.restore.duplicate'));
        }

        $sim->restore();

        return redirect()->route('sims.show', $sim)
            ->with('success', trans('admin/sims/message.restore.success'));
    }

    /**
     * Custom field inputs for the selected plan, swapped into the SIM form
     * when the plan dropdown changes (same idea as the asset form's
     * model-dependent custom fields).
     */
    public function customFields(Request $request): View
    {
        if (! Gate::any(['create', 'update'], Sim::class)) {
            abort(403);
        }

        $item = $request->filled('sim_id') ? Sim::findOrFail((int) $request->input('sim_id')) : new Sim;

        if ($item->exists) {
            $this->authorize('update', $item);
        }

        $item->sim_plan_id = $request->filled('sim_plan_id') ? (int) $request->input('sim_plan_id') : null;

        return view('sims/partials/custom-fields', ['item' => $item]);
    }

    /**
     * Clone everything except the per-card identifiers and secrets.
     */
    public function getClone(Sim $sim): View
    {
        $this->authorize('clone', $sim);

        $cloned = $sim->replicate([
            'iccid',
            'msisdn',
            'imsi',
            'pin',
            'puk',
            'activation_code',
            'assigned_to',
            'assigned_type',
            'last_checkout',
            'last_checkin',
            'expected_checkin',
            'created_by',
        ]);
        $cloned->id = null;

        return view('sims/edit')
            ->with('item', $cloned);
    }
}
