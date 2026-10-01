<?php

namespace App\Actions\Sims;

use App\Exceptions\CheckoutNotAllowed;
use App\Http\Requests\SaveSimRequest;
use App\Models\Company;
use App\Models\Sim;
use App\Models\SimPlan;
use App\Models\User;
use App\Services\PhoneNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Applies validated SIM input (UI form or API payload) to a Sim and saves it.
 *
 * Secret handling:
 *  - A blank PIN/PUK/activation code in the UI form keeps the stored value,
 *    so editors who can't read secrets don't wipe them by saving the form.
 *    The form sends clear_<field>=1 to explicitly remove a secret.
 *  - In the API, sending the key with null/"" clears it; omitting it keeps it.
 *
 * Custom fields:
 *  - Only keys present in the request are touched. Encrypted fields are not
 *    rendered as inputs for users without viewSecrets, so they are kept.
 *  - Users without viewSecrets can never modify encrypted custom fields.
 */
class SaveSimAction
{
    private const PLAIN_FIELDS = [
        'iccid',
        'imsi',
        'sim_type',
        'sim_plan_id',
        'supplier_id',
        'account_number',
        'department_id',
        'activation_date',
        'expiration_date',
        'notes',
    ];

    /**
     * Not on the SIM form (API/import only): only changed when sent, so
     * saving the form never wipes them. Location follows the owner on
     * checkout; status is managed internally (default status).
     */
    private const OPTIONAL_FIELDS = [
        'name',
        'status_id',
        'location_id',
    ];

    public static function run(Sim $sim, SaveSimRequest $request): bool
    {
        $isApi = $request->is('api/*');
        $partial = $request->isPartialUpdate();

        foreach (self::PLAIN_FIELDS as $field) {
            if ($partial && ! $request->exists($field)) {
                continue;
            }

            $value = $request->input($field);
            $sim->{$field} = ($value === '') ? null : $value;
        }

        foreach (self::OPTIONAL_FIELDS as $field) {
            if ($request->exists($field)) {
                $value = $request->input($field);
                $sim->{$field} = ($value === '') ? null : $value;
            }
        }

        // The operator defaults to the plan's when left empty.
        if ($sim->sim_plan_id && ($plan = SimPlan::find($sim->sim_plan_id))) {
            $sim->supplier_id ??= $plan->supplier_id;
        }

        if (! $partial || $request->exists('company_id')) {
            $sim->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        }

        // Phone number: stored in E.164 for the selected (or default) country.
        if (! $partial || $request->exists('msisdn') || $request->exists('phone_country')) {
            $country = PhoneNumberService::cleanCountry($request->input('phone_country', $sim->phone_country))
                ?? PhoneNumberService::defaultCountry();
            $raw = $request->exists('msisdn') ? $request->input('msisdn') : $sim->msisdn;

            $sim->msisdn = ($raw === null || $raw === '') ? null : (PhoneNumberService::toE164($raw, $country) ?? $raw);
            $sim->phone_country = $sim->msisdn ? (PhoneNumberService::regionOf($sim->msisdn) ?? $country) : $country;
        }

        foreach (Sim::SECRET_FIELDS as $secret) {
            if ($request->boolean('clear_'.$secret)) {
                $sim->{$secret} = null;

                continue;
            }

            if ($request->filled($secret)) {
                $sim->{$secret} = $request->input($secret);
            } elseif ($isApi && $request->exists($secret)) {
                $sim->{$secret} = null;
            }
        }

        $canEditEncrypted = Gate::allows('viewSecrets', $sim->exists ? $sim : Sim::class);

        // The fields of the SIM's (possibly just changed) plan.
        foreach ($sim->customFields() as $field) {
            if (! $request->exists($field->db_column)) {
                continue;
            }

            if (($field->field_encrypted == '1') && ! $canEditEncrypted) {
                continue;
            }

            $sim->setCustomFieldValue($field, $request->input($field->db_column));
        }

        if (! $sim->exists) {
            $sim->created_by = auth()->id();
            // Like assets: a new SIM always has a status.
            $sim->status_id ??= Sim::defaultStatusId();
        }

        $newOwner = self::requestedOwner($sim, $request);

        // Department defaults to the (new) owner's department.
        if (! $sim->department_id && ($owner = $newOwner ?: ($sim->checkedOutToUser() ? $sim->assignedTo : null))) {
            $sim->department_id = $owner->department_id;
        }

        // Saving the card and changing its owner succeed or fail together.
        return DB::transaction(function () use ($sim, $newOwner, $request): bool {
            if (! $sim->save()) {
                return false;
            }

            if ($newOwner !== false) {
                self::changeOwner($sim, $newOwner, $request->input('note'));
            }

            return true;
        });
    }

    /**
     * The owner requested by the form/API: a User, null to unassign, or
     * false for "no change". An empty dropdown only unassigns a SIM that is
     * checked out to a user; SIMs installed in an asset or at a location are
     * left alone (the dropdown can't show those targets).
     */
    private static function requestedOwner(Sim $sim, SaveSimRequest $request): User|null|false
    {
        if (! $request->exists('assigned_user')) {
            return false;
        }

        $userId = (int) $request->input('assigned_user');

        if (! $userId) {
            return $sim->checkedOutToUser() ? null : false;
        }

        if ($sim->checkedOutToUser() && ((int) $sim->assigned_to === $userId)) {
            return false;
        }

        return User::findOrFail($userId);
    }

    /**
     * Check the SIM in from its current owner and out to the new one, with
     * the regular checkout/checkin events, history and permission checks.
     *
     * @throws ValidationException
     */
    private static function changeOwner(Sim $sim, ?User $owner, ?string $note): void
    {
        $note = $note ?: trans('admin/sims/general.owner_changed_in_card');

        if ($sim->assigned_to) {
            if (Gate::denies('checkin', $sim) || ! $sim->checkIn($note)) {
                throw ValidationException::withMessages(['assigned_user' => trans('admin/sims/message.checkin.error')]);
            }
            $sim->refresh();
        }

        if ($owner === null) {
            return;
        }

        try {
            if (Gate::denies('checkout', $sim) || ! $sim->checkOut($owner, auth()->user(), null, null, $note)) {
                throw ValidationException::withMessages(['assigned_user' => trans('admin/sims/message.checkout.error')]);
            }
        } catch (CheckoutNotAllowed $e) {
            throw ValidationException::withMessages(['assigned_user' => $e->getMessage()]);
        }
    }
}
