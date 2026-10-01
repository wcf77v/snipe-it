@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/sims/general.settings') }}
    @parent
@stop

{{-- Page content --}}
@section('content')

<x-container class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1 col-sm-12 col-sm-offset-0">

    <x-form :route="route('sims.settings.update')">

        <x-box>
            <x-slot:header>{{ trans('admin/sims/general.settings') }}</x-slot:header>

            {{-- Form fields --}}
            <fieldset name="sim-form-fields">
                <x-form.legend :help_text="trans('admin/sims/general.required_fields_help')">
                    <x-icon type="checkmark" class="fa-fw" />
                    {{ trans('admin/sims/general.form_fields') }}
                </x-form.legend>

                <x-form.row :label="trans('admin/sims/general.required_fields')" name="sim_required_fields" input_div_class="col-md-7">
                    <x-slot:input>
                        @foreach (\App\Models\Sim::REQUIRABLE_FIELDS as $requirable)
                            <label class="form-control">
                                {{-- label.form-control is a 2-column grid: checkbox + ONE text element. --}}
                                <input type="checkbox" name="sim_required_fields[]" value="{{ $requirable }}" @checked(in_array($requirable, old('sim_required_fields', $requiredFields)))>
                                <span>{{ trans('admin/sims/general.field_'.$requirable) }}</span>
                            </label>
                        @endforeach
                    </x-slot:input>
                </x-form.row>
            </fieldset>

            {{-- Notifications --}}
            <fieldset name="sim-notifications">
                <x-form.legend :help_text="trans('admin/sims/general.notifications_help')">
                    <x-icon type="email" class="fa-fw" />
                    {{ trans('admin/sims/general.notifications') }}
                </x-form.legend>

                <div class="form-group">
                    <div class="col-md-7 col-md-offset-3">
                        <label class="form-control">
                            <input type="checkbox" name="sim_checkout_email" value="1" @checked(old('sim_checkout_email', $snipeSettings->sim_checkout_email))>
                            <span>{{ trans('admin/sims/general.checkout_email') }}</span>
                        </label>
                    </div>
                </div>
            </fieldset>

            {{-- Custom fields --}}
            <x-form.row :label="trans('admin/sims/general.default_fieldset')" name="sim_fieldset_id" input_div_class="col-md-7"
                :help_text="trans('admin/sims/general.fieldset_help')">
                <x-slot:input>
                    <x-input.select
                        name="sim_fieldset_id"
                        id="sim_fieldset_id"
                        :options="['' => trans('admin/sims/general.no_fieldset')] + $fieldsets->all()"
                        :selected="old('sim_fieldset_id', $current)"
                        style="width: 100%;"
                        aria-label="sim_fieldset_id"
                    />
                </x-slot:input>
            </x-form.row>

            <div class="form-group">
            <div class="col-md-9 col-md-offset-3">
                <a href="{{ route('simplans.index') }}">
                    <x-icon type="sims" class="fa-fw" />
                    {{ trans('admin/sims/general.plans') }}
                </a>
                @can('view', \App\Models\CustomField::class)
                    &nbsp;·&nbsp;
                    <a href="{{ route('fields.index') }}">
                        <x-icon type="settings" class="fa-fw" />
                        {{ trans('admin/custom_fields/general.custom_fields') }}
                    </a>
                @endcan
            </div>
            </div>

            {{-- Phone numbers --}}
            <fieldset name="sim-phone-numbers">
                <x-form.legend :help_text="trans('admin/sims/general.phone_settings_help')">
                    <x-icon type="phone" class="fa-fw" />
                    {{ trans('admin/sims/general.phone_settings') }}
                </x-form.legend>

                <x-form.row :label="trans('admin/sims/general.default_country')" name="sim_default_country" input_div_class="col-md-5"
                    :help_text="trans('admin/sims/general.default_country_help')">
                    <x-slot:input>
                        <x-input.country-select name="sim_default_country" id="sim_default_country" :selected="old('sim_default_country', $defaultCountry)" />
                    </x-slot:input>
                </x-form.row>

                <x-form.row :label="trans('admin/sims/general.match_fields')" name="sim_match_fields" input_div_class="col-md-7"
                    :help_text="trans('admin/sims/general.match_fields_help')">
                    <x-slot:input>
                        @foreach (array_keys(\App\Services\SimPhoneMatcher::FIELDS) as $matchField)
                            <label class="form-control">
                                {{-- label.form-control is a 2-column grid: checkbox + ONE text element. --}}
                                <input type="checkbox" name="sim_match_fields[]" value="{{ $matchField }}" @checked(in_array($matchField, old('sim_match_fields', $matchFields)))>
                                <span>
                                    {{ trans('admin/sims/general.match_field_'.$matchField) }}
                                    <small class="text-muted">(LDAP: {{ ($matchField === 'phone' ? $ldapPhoneField : $ldapMobileField) ?: trans('admin/sims/general.ldap_not_mapped') }} · SCIM: phoneNumbers[{{ $matchField === 'phone' ? 'work' : 'mobile' }}])</small>
                                </span>
                            </label>
                        @endforeach
                    </x-slot:input>
                </x-form.row>

                <x-form.row :label="trans('admin/sims/general.writeback')" name="sim_profile_writeback" input_div_class="col-md-7"
                    :help_text="trans('admin/sims/general.writeback_help')">
                    <x-slot:input>
                        <x-input.select
                            name="sim_profile_writeback"
                            id="sim_profile_writeback"
                            :options="[
                                'off' => trans('admin/sims/general.writeback_off'),
                                'empty' => trans('admin/sims/general.writeback_empty'),
                                'always' => trans('admin/sims/general.writeback_always'),
                            ]"
                            :selected="old('sim_profile_writeback', $writeback)"
                            style="width: 100%;"
                            aria-label="sim_profile_writeback"
                        />
                    </x-slot:input>
                </x-form.row>
            </fieldset>

        </x-box>

    </x-form>

    {{-- Separate form: recompute users' normalized numbers. --}}
    <x-box>
        <x-slot:header>{{ trans('admin/sims/general.normalize_title') }}</x-slot:header>
        <p>{{ trans('admin/sims/general.normalize_help') }}</p>
        <form method="POST" action="{{ route('sims.settings.normalize') }}">
            @csrf
            <button type="submit" class="btn btn-default">
                <x-icon type="phone" class="fa-fw" />
                {{ trans('admin/sims/general.normalize_button') }}
            </button>
            <span class="text-muted" style="margin-left: 10px;"><code>php artisan snipeit:sims-normalize-phones</code></span>
        </form>
    </x-box>

</x-container>

@stop
