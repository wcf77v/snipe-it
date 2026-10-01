<?php

namespace App\Http\Controllers\Sims;

use App\Exceptions\CheckoutNotAllowed;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SimCheckoutRequest;
use App\Http\Traits\CheckInOutTrait;
use App\Models\Sim;
use App\Services\SimPhoneMatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SimCheckoutController extends Controller
{
    use CheckInOutTrait;

    public function create(Sim $sim): View|RedirectResponse
    {
        $this->authorize('checkout', $sim);

        if (! $sim->availableForCheckout()) {
            return redirect()->route('sims.show', $sim)
                ->with('error', trans('admin/sims/message.checkout.not_available'));
        }

        // Pre-select a user: explicitly requested (?assigned_user=, e.g. from
        // a phone match), otherwise the single user whose AD number matches.
        $phoneMatches = SimPhoneMatcher::usersForSim($sim);
        $suggestedUserId = request()->integer('assigned_user')
            ?: ($phoneMatches->count() === 1 ? $phoneMatches->first()->id : null);

        return view('sims/checkout', [
            'sim' => $sim,
            'item' => $sim,
            'phoneMatches' => $phoneMatches,
            'suggestedUserId' => $suggestedUserId,
        ]);
    }

    public function store(SimCheckoutRequest $request, Sim $sim): RedirectResponse
    {
        $this->authorize('checkout', $sim);

        // FMCS compatibility between the SIM and the target is enforced in
        // Sim::checkOut() via canCheckoutTo().
        $target = $this->determineCheckoutTarget();

        session()->put([
            'redirect_option' => $request->input('redirect_option'),
            'checkout_to_type' => $request->input('checkout_to_type'),
        ]);

        try {
            // Lock the row so two concurrent submits can't both check the
            // same SIM out (same guard as AssetCheckoutController).
            $checkedOut = DB::transaction(function () use ($sim, $target, $request): bool {
                $locked = Sim::whereKey($sim->id)->lockForUpdate()->first();
                if (! $locked || ! $locked->availableForCheckout()) {
                    return false;
                }

                if ($request->filled('status_id')) {
                    $locked->status_id = (int) $request->input('status_id');
                }

                return $locked->checkOut(
                    $target,
                    auth()->user(),
                    $request->input('checkout_at') ?: null,
                    $request->input('expected_checkin') ?: null,
                    $request->input('note'),
                );
            });
        } catch (CheckoutNotAllowed $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        if (! $checkedOut) {
            return redirect()->route('sims.show', $sim)
                ->with('error', trans('admin/sims/message.checkout.error'));
        }

        return Helper::getRedirectOption($request, $sim->id, 'Sims')
            ->with('success', trans('admin/sims/message.checkout.success'));
    }
}
