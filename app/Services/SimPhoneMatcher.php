<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Setting;
use App\Models\Sim;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Matches SIM card numbers against the phone numbers on user profiles.
 *
 * users.phone / users.mobile are free text (LDAP sync fills them from AD,
 * typically from telephoneNumber and mobile). They are normalized to E.164
 * into users.phone_e164 / users.mobile_e164 whenever a user is saved (see
 * SimUserPhoneObserver) or by `php artisan snipeit:sims-normalize-phones`,
 * so a match is an indexed equality lookup against sims.msisdn.
 *
 * Nothing here checks anything out on its own: callers show the matches
 * and the user confirms.
 */
class SimPhoneMatcher
{
    /**
     * User profile fields that can be matched, mapped to their E.164 column.
     */
    public const FIELDS = [
        'phone' => 'phone_e164',
        'mobile' => 'mobile_e164',
    ];

    public const WRITEBACK_OFF = 'off';

    public const WRITEBACK_EMPTY = 'empty';

    public const WRITEBACK_ALWAYS = 'always';

    /**
     * The profile fields enabled for matching in the SIM settings.
     *
     * @return string[]
     */
    public static function enabledFields(): array
    {
        $configured = Setting::getSettings()?->sim_match_fields ?? 'phone,mobile';
        $fields = array_values(array_intersect(array_map('trim', explode(',', $configured)), array_keys(self::FIELDS)));

        return $fields;
    }

    /**
     * Compute the E.164 columns for a user from their phone/mobile and
     * country (falling back to the SIM default country).
     *
     * @return array{phone_e164: ?string, mobile_e164: ?string}
     */
    public static function normalizedUserNumbers(User $user): array
    {
        $country = PhoneNumberService::cleanCountry($user->country) ?? PhoneNumberService::defaultCountry();

        return [
            'phone_e164' => PhoneNumberService::toE164($user->phone, $country),
            'mobile_e164' => PhoneNumberService::toE164($user->mobile, $country),
        ];
    }

    /**
     * Recompute users.phone_e164 / mobile_e164 for every user, without
     * touching updated_at or writing action log entries.
     *
     * @return int Number of users whose normalized numbers changed.
     */
    public static function normalizeAllUsers(): int
    {
        $changed = 0;

        User::withTrashed()
            ->select(['id', 'phone', 'mobile', 'country', 'phone_e164', 'mobile_e164'])
            ->chunkById(500, function ($users) use (&$changed) {
                foreach ($users as $user) {
                    $numbers = self::normalizedUserNumbers($user);

                    if (($numbers['phone_e164'] !== $user->phone_e164) || ($numbers['mobile_e164'] !== $user->mobile_e164)) {
                        DB::table('users')->where('id', $user->id)->update($numbers);
                        $changed++;
                    }
                }
            });

        return $changed;
    }

    /**
     * Users (visible to the current user) whose enabled profile numbers
     * equal the SIM's number. Each user gets a `sim_match_field` attribute
     * naming the field that matched ("phone" or "mobile").
     *
     * @return Collection<int, User>
     */
    public static function usersForSim(Sim $sim): Collection
    {
        $fields = self::enabledFields();

        if (! $sim->msisdn || empty($fields)) {
            return collect();
        }

        $query = self::visibleUsers()->where(function (Builder $query) use ($fields, $sim) {
            foreach ($fields as $field) {
                $query->orWhere('users.'.self::FIELDS[$field], $sim->msisdn);
            }
        });

        return $query->get()->each(function (User $user) use ($fields, $sim) {
            foreach ($fields as $field) {
                if ($user->{self::FIELDS[$field]} === $sim->msisdn) {
                    $user->setAttribute('sim_match_field', $field);
                    break;
                }
            }
        });
    }

    /**
     * SIMs (visible to the current user) whose number matches one of the
     * user's enabled profile numbers. Each SIM gets a `sim_match_field`
     * attribute.
     *
     * @return Collection<int, Sim>
     */
    public static function simsForUser(User $user): Collection
    {
        $numbers = [];

        foreach (self::enabledFields() as $field) {
            if ($number = $user->{self::FIELDS[$field]}) {
                $numbers[$number] = $field;
            }
        }

        if (empty($numbers)) {
            return collect();
        }

        return Sim::with('assignedTo', 'supplier')
            ->whereIn('sims.msisdn', array_keys($numbers))
            ->get()
            ->each(fn (Sim $sim) => $sim->setAttribute('sim_match_field', $numbers[$sim->msisdn] ?? null));
    }

    /**
     * Every SIM/user pair whose numbers match but where the SIM is not
     * checked out to that user (unassigned, or checked out to someone or
     * something else).
     *
     * @return Collection<int, array{sim: Sim, user: User, field: string, status: string}>
     */
    public static function pendingMatches(): Collection
    {
        $fields = self::enabledFields();

        if (empty($fields)) {
            return collect();
        }

        $sims = Sim::with('assignedTo', 'supplier')->whereNotNull('sims.msisdn')->get()->keyBy('msisdn');

        if ($sims->isEmpty()) {
            return collect();
        }

        $users = self::visibleUsers()->where(function (Builder $query) use ($fields, $sims) {
            foreach ($fields as $field) {
                $query->orWhereIn('users.'.self::FIELDS[$field], $sims->keys());
            }
        })->get();

        $matches = collect();

        foreach ($users as $user) {
            foreach ($fields as $field) {
                $number = $user->{self::FIELDS[$field]};

                if (! $number || ! ($sim = $sims->get($number))) {
                    continue;
                }

                if ($sim->checkedOutToUser() && ((int) $sim->assigned_to === (int) $user->id)) {
                    continue;
                }

                $matches->push([
                    'sim' => $sim,
                    'user' => $user,
                    'field' => $field,
                    'status' => $sim->assigned_to ? 'assigned_elsewhere' : 'available',
                ]);
            }
        }

        return $matches->unique(fn ($match) => $match['sim']->id.'-'.$match['user']->id)->values();
    }

    /**
     * After a SIM is checked out to a user, copy its number into the user's
     * mobile field depending on the "write back" setting. Note that LDAP
     * sync is read-only: this never reaches AD, and the next sync may
     * overwrite the field again if AD has a different value.
     */
    public static function writeBackToUser(Sim $sim, User $user): bool
    {
        $mode = Setting::getSettings()?->sim_profile_writeback ?? self::WRITEBACK_OFF;

        if (! $sim->msisdn || ($mode === self::WRITEBACK_OFF)) {
            return false;
        }

        // Checking out a SIM must not become a way to edit user profiles:
        // only write when the acting user may edit this user anyway.
        if (! Gate::allows('update', $user)) {
            return false;
        }

        if (($mode === self::WRITEBACK_EMPTY) && trim((string) $user->mobile) !== '') {
            return false;
        }

        if ($user->mobile_e164 === $sim->msisdn) {
            return false;
        }

        $user->mobile = PhoneNumberService::format($sim->msisdn);

        return $user->save();
    }

    /**
     * Non-deleted users, restricted to the current user's companies when
     * full multiple company support is on.
     */
    private static function visibleUsers(): Builder
    {
        return Company::scopeCompanyables(User::query()->select('users.*'), 'company_id', 'users');
    }
}
