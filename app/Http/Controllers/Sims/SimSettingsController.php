<?php

namespace App\Http\Controllers\Sims;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSimSettingsRequest;
use App\Models\CustomFieldset;
use App\Models\Setting;
use App\Models\Sim;
use App\Services\PhoneNumberService;
use App\Services\SimPhoneMatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Module-level settings for SIM cards: default custom fieldset, phone number
 * country, and how SIM numbers are matched to user profiles (AD).
 */
class SimSettingsController extends Controller
{
    public function edit(): View
    {
        $this->authorize('manageSettings', Sim::class);

        $settings = Setting::getSettings();

        return view('sims/settings')
            ->with('fieldsets', CustomFieldset::orderBy('name')->pluck('name', 'id'))
            ->with('current', $settings->sim_fieldset_id)
            ->with('defaultCountry', PhoneNumberService::defaultCountry())
            ->with('matchFields', SimPhoneMatcher::enabledFields())
            ->with('writeback', $settings->sim_profile_writeback ?? SimPhoneMatcher::WRITEBACK_OFF)
            ->with('requiredFields', Sim::requiredFields())
            ->with('ldapPhoneField', $settings->ldap_phone_field)
            ->with('ldapMobileField', $settings->ldap_mobile);
    }

    public function update(StoreSimSettingsRequest $request): RedirectResponse
    {
        $this->authorize('manageSettings', Sim::class);

        $settings = Setting::getSettings();
        $countryChanged = PhoneNumberService::cleanCountry($request->input('sim_default_country')) !== PhoneNumberService::cleanCountry($settings->sim_default_country);

        $settings->sim_fieldset_id = $request->input('sim_fieldset_id') ?: null;
        $settings->sim_default_country = PhoneNumberService::cleanCountry($request->input('sim_default_country'));
        $settings->sim_match_fields = implode(',', $request->input('sim_match_fields', []));
        $settings->sim_profile_writeback = $request->input('sim_profile_writeback');
        $settings->sim_required_fields = implode(',', $request->input('sim_required_fields', []));
        $settings->sim_checkout_email = $request->boolean('sim_checkout_email');

        if (! $settings->save()) {
            return redirect()->back()->withInput()->withErrors($settings->getErrors());
        }

        Sim::flushCustomFieldset();

        // User numbers without a country are read with the default one.
        if ($countryChanged) {
            SimPhoneMatcher::normalizeAllUsers();
        }

        return redirect()->route('sims.settings.edit')
            ->with('success', trans('admin/sims/message.settings.success'));
    }

    /**
     * Recompute users' normalized phone numbers (same as the
     * snipeit:sims-normalize-phones command).
     */
    public function normalize(): RedirectResponse
    {
        $this->authorize('manageSettings', Sim::class);

        $changed = SimPhoneMatcher::normalizeAllUsers();

        return redirect()->route('sims.settings.edit')
            ->with('success', trans('admin/sims/message.settings.normalized', ['count' => $changed]));
    }
}
