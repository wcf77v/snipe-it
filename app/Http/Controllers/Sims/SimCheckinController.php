<?php

namespace App\Http\Controllers\Sims;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SimCheckinRequest;
use App\Models\Sim;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SimCheckinController extends Controller
{
    public function create(Sim $sim): View|RedirectResponse
    {
        $this->authorize('checkin', $sim);

        if (! $sim->availableForCheckIn()) {
            return redirect()->route('sims.show', $sim)
                ->with('error', trans('admin/sims/message.checkin.not_assigned'));
        }

        return view('sims/checkin', ['sim' => $sim, 'item' => $sim]);
    }

    public function store(SimCheckinRequest $request, Sim $sim): RedirectResponse
    {
        $this->authorize('checkin', $sim);

        // Remember where the SIM was so "back to checked-out target" works.
        session()->put([
            'redirect_option' => $request->input('redirect_option'),
            'checkout_to_type' => $sim->assignedType(),
            'checkedInFrom' => $sim->assigned_to,
        ]);

        $checkedIn = DB::transaction(function () use ($sim, $request): bool {
            $locked = Sim::whereKey($sim->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->availableForCheckIn()) {
                return false;
            }

            if ($request->filled('status_id')) {
                $locked->status_id = (int) $request->input('status_id');
            }

            return $locked->checkIn(
                $request->input('note'),
                $request->input('checkin_at') ?: null,
                $request->input('location_id') ? (int) $request->input('location_id') : null,
            );
        });

        if (! $checkedIn) {
            return redirect()->route('sims.show', $sim)
                ->with('error', trans('admin/sims/message.checkin.error'));
        }

        return Helper::getRedirectOption($request, $sim->id, 'Sims')
            ->with('success', trans('admin/sims/message.checkin.success'));
    }
}
