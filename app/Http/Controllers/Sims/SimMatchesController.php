<?php

namespace App\Http\Controllers\Sims;

use App\Exceptions\CheckoutNotAllowed;
use App\Http\Controllers\Controller;
use App\Http\Requests\LinkSimMatchesRequest;
use App\Models\Sim;
use App\Models\User;
use App\Services\SimPhoneMatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * SIM cards whose number matches a user's phone/mobile (from AD) but which
 * are not checked out to that user yet, with bulk "link" (check out).
 */
class SimMatchesController extends Controller
{
    public function index(): View
    {
        $this->authorize('index', Sim::class);
        // The page maps phone numbers to people.
        $this->authorize('view', User::class);

        return view('sims/matches', [
            'matches' => SimPhoneMatcher::pendingMatches(),
            'fields' => SimPhoneMatcher::enabledFields(),
        ]);
    }

    /**
     * Check out each selected SIM to its matched user. SIMs that are
     * checked out to someone else, or no longer match, are skipped.
     */
    public function store(LinkSimMatchesRequest $request): RedirectResponse
    {
        $this->authorize('checkout', Sim::class);

        $linked = 0;
        $skipped = 0;

        foreach ($request->input('matches', []) as $pair) {
            [$simId, $userId] = array_map('intval', explode(':', $pair, 2) + [1 => 0]);

            $sim = Sim::find($simId);
            $user = User::find($userId);

            // Re-check the match server-side: never trust the pair as sent.
            if (! $sim || ! $user || ! SimPhoneMatcher::usersForSim($sim)->contains('id', $user->id)) {
                $skipped++;

                continue;
            }

            if (! $this->linkSimToUser($sim, $user, $request->input('note'))) {
                $skipped++;

                continue;
            }

            $linked++;
        }

        $redirect = redirect()->route('sims.matches.index');

        if ($linked > 0) {
            $redirect->with('success', trans('admin/sims/message.matches.linked', ['count' => $linked]));
        }

        if ($skipped > 0) {
            $redirect->with('warning', trans('admin/sims/message.matches.skipped', ['count' => $skipped]));
        }

        return $redirect;
    }

    private function linkSimToUser(Sim $sim, User $user, ?string $note): bool
    {
        if (! auth()->user()->can('checkout', $sim)) {
            return false;
        }

        try {
            return DB::transaction(function () use ($sim, $user, $note): bool {
                $locked = Sim::whereKey($sim->id)->lockForUpdate()->first();

                if (! $locked || ! $locked->availableForCheckout()) {
                    return false;
                }

                return $locked->checkOut($user, auth()->user(), null, null, $note ?: trans('admin/sims/general.matched_by_phone'));
            });
        } catch (CheckoutNotAllowed $e) {
            return false;
        }
    }
}
