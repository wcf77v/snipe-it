@extends('layouts/default')

{{-- Page title --}}
@section('title')
    @if ($item->id)
        {{ trans('admin/sims/general.update') }}
    @else
        {{ trans('admin/sims/general.create') }}
    @endif
    @parent
@stop

@php
    // Which fields the admin made required in the SIM settings.
    $req = fn (string $field): bool => \App\Models\Sim::isFieldRequired($field);
    $phoneCountry = old('phone_country', $item->phone_country ?? \App\Services\PhoneNumberService::defaultCountry());
    $currentUserId = $item->checkedOutToUser() ? $item->assigned_to : null;
    $assignedElsewhere = $item->assigned_to && ! $item->checkedOutToUser();
@endphp

{{-- Page content --}}
@section('content')

<x-container class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1 col-sm-12 col-sm-offset-0">

    <x-form :$item route="{{ isset($item->id) ? route('sims.update', $item->id) : route('sims.store') }}">

        <x-box top_submit>
            @if ($item->id)
                <x-slot:header>{{ $item->display_name }}</x-slot:header>
            @endif

            {{-- ============ 1. Main ============ --}}
            <fieldset name="sim-main">
                <x-form.legend>
                    <x-icon type="sims" class="fa-fw" />
                    {{ trans('admin/sims/general.section_main') }}
                </x-form.legend>

                <x-form.row :label="trans('admin/sims/table.phone_country')" name="phone_country" input_div_class="col-md-5">
                    <x-slot:input>
                        <x-input.country-select name="phone_country" id="phone_country" :selected="$phoneCountry" />
                    </x-slot:input>
                </x-form.row>

                {{-- Any local notation is accepted and stored in international format. --}}
                <x-form.row :label="trans('admin/sims/table.msisdn')" name="msisdn" input_div_class="col-md-5"
                    :help_text="trans('admin/sims/general.msisdn_help')">
                    <x-slot:input>
                        <div class="input-group">
                            <input type="tel" class="form-control" id="msisdn" name="msisdn" maxlength="32"
                                   value="{{ old('msisdn', $item->formatted_msisdn) }}"
                                   placeholder="{{ \App\Services\PhoneNumberService::example($phoneCountry) }}"
                                   aria-label="{{ trans('admin/sims/table.msisdn') }}" @required($req('msisdn'))>
                            <span class="input-group-addon"><x-icon type="phone" /></span>
                        </div>
                    </x-slot:input>
                </x-form.row>

                {{-- Operator comes from the plan (tariff); no separate field on the form. --}}
                <div class="form-group{{ $errors->has('sim_plan_id') ? ' has-error' : '' }}">
                    <label for="sim_plan_id_select" class="col-md-3 control-label">{{ trans('admin/sims/table.plan') }}</label>
                    <div class="col-md-7">
                        <select class="js-data-ajax" data-endpoint="simplans" data-placeholder="{{ trans('admin/sims/general.select_plan') }}"
                                name="sim_plan_id" id="sim_plan_id_select" style="width: 100%" aria-label="sim_plan_id" @required($req('sim_plan_id'))>
                            @php $planId = old('sim_plan_id', $item->sim_plan_id); @endphp
                            @if ($planId && ($selectedPlan = \App\Models\SimPlan::find($planId)))
                                <option value="{{ $selectedPlan->id }}" selected="selected">{{ $selectedPlan->name }}</option>
                            @else
                                <option value="">{{ trans('admin/sims/general.select_plan') }}</option>
                            @endif
                        </select>
                    </div>
                    @can('create', \App\Models\SimPlan::class)
                        <div class="col-md-1 col-sm-1 text-left">
                            <a href="{{ route('simplans.create') }}" target="_blank" class="btn btn-sm btn-theme">{{ trans('button.new') }}</a>
                        </div>
                    @endcan
                    <div class="col-md-9 col-md-offset-3">
                        <x-form.error name="sim_plan_id" />
                        <p class="help-block">
                            {{ trans('admin/sims/general.plan_help') }}
                            @if ($item->simPlan && ($item->simPlan->monthly_cost !== null))
                                <br><strong>{{ trans('admin/sims/table.monthly_cost') }}:</strong>
                                {{ $snipeSettings->default_currency }} {{ Helper::formatCurrencyOutput($item->simPlan->monthly_cost) }}
                            @endif
                        </p>
                    </div>
                </div>

                <x-form.row :label="trans('admin/sims/table.iccid')" name="iccid" input_div_class="col-md-7"
                    :help_text="trans('admin/sims/general.iccid_help')">
                    <x-slot:input>
                        <input type="text" class="form-control" id="iccid" name="iccid" maxlength="32" inputmode="numeric"
                               value="{{ old('iccid', $item->iccid) }}" aria-label="iccid" @required($req('iccid'))>
                    </x-slot:input>
                </x-form.row>

                <x-form.row :label="trans('admin/sims/table.sim_type')" name="sim_type" input_div_class="col-md-4">
                    <x-slot:input>
                        <x-input.select
                            name="sim_type"
                            id="sim_type"
                            :options="[
                                'physical' => trans('admin/sims/general.type_physical'),
                                'esim' => trans('admin/sims/general.type_esim'),
                            ]"
                            :selected="old('sim_type', $item->sim_type ?? 'physical')"
                            style="width: 100%;"
                            aria-label="sim_type"
                        />
                    </x-slot:input>
                </x-form.row>
            </fieldset>

            {{-- ============ 2. Owner ============ --}}
            <fieldset name="sim-owner">
                <x-form.legend :help_text="trans('admin/sims/general.section_owner_help')">
                    <x-icon type="user" class="fa-fw" />
                    {{ trans('admin/sims/general.section_owner') }}
                </x-form.legend>

                <x-input.company-select
                    :label="trans('general.company')"
                    name="company_id"
                    :selected="old('company_id', $item->company_id)"
                />

                @if ($assignedElsewhere && $item->assignedTo)
                    <x-form.static :label="trans('admin/hardware/form.checkedout_to')">
                        {{ $item->assignedTo->display_name }}
                        ({{ trans('admin/sims/general.assigned_to_'.$item->assignedType()) }})
                    </x-form.static>
                @endif

                @can('checkout', \App\Models\Sim::class)
                    <x-input.user-select
                        :label="trans('general.user')"
                        name="assigned_user"
                        :selected="old('assigned_user', $currentUserId)"
                        :companyId="$item->company_id"
                        :required="$req('assigned_user') && ! $assignedElsewhere"
                        :hideNewButton="true"
                    />                @elseif ($currentUserId && $item->assignedTo)
                    <x-form.static :label="trans('general.user')">{{ $item->assignedTo->display_name }}</x-form.static>
                @endif

                <x-input.department-select
                    :label="trans('general.department')"
                    name="department_id"
                    :selected="old('department_id', $item->department_id)"
                    :required="$req('department_id')"
                />
                <div class="form-group">
                    <div class="col-md-9 col-md-offset-3">
                        <p class="help-block">{{ trans('admin/sims/general.department_help') }}</p>
                    </div>
                </div>
            </fieldset>

            {{-- ============ 3. Additional ============ --}}
            <fieldset name="sim-additional">
                <x-form.legend>
                    <x-icon type="info-circle" class="fa-fw" />
                    {{ trans('admin/sims/general.section_additional') }}
                </x-form.legend>

                @foreach (['account_number' => 191, 'imsi' => 15] as $textField => $textMax)
                    <x-form.row :label="trans('admin/sims/table.'.$textField)" :name="$textField" input_div_class="col-md-7">
                        <x-slot:input>
                            <input type="text" class="form-control" id="{{ $textField }}" name="{{ $textField }}" maxlength="{{ $textMax }}"
                                   value="{{ old($textField, $item->{$textField}) }}" aria-label="{{ $textField }}" @required($req($textField))>
                        </x-slot:input>
                    </x-form.row>
                @endforeach

                @foreach (['activation_date', 'expiration_date'] as $dateField)
                    <x-form.row :label="trans('admin/sims/table.'.$dateField)" :name="$dateField" input_div_class="col-md-4">
                        <x-slot:input>
                            <x-input.datepicker
                                :name="$dateField"
                                :id="$dateField"
                                :value="old($dateField, $item->{$dateField} ? $item->{$dateField}->format('Y-m-d') : '')"
                                :required="$req($dateField) ? '1' : ''"
                            />
                        </x-slot:input>
                    </x-form.row>
                @endforeach

                <x-form.row
                    :label="trans('general.notes')"
                    :$item
                    name="notes"
                    type="textarea"
                />
            </fieldset>

            {{-- ============ 4. Security codes: never pre-filled, blank keeps the stored value ============ --}}
            <fieldset name="sim-secrets">
                <x-form.legend :help_text="trans('admin/sims/general.secrets_help')">
                    <x-icon type="locked" class="fa-fw" />
                    {{ trans('admin/sims/general.secrets') }}
                </x-form.legend>

                @foreach (['pin' => 8, 'puk' => 8, 'activation_code' => 1024] as $secret => $secretMaxLength)
                    <x-form.row :label="trans('admin/sims/table.'.$secret)" :name="$secret" input_div_class="col-md-5">
                        <x-slot:input>
                            <input
                                type="password"
                                class="form-control"
                                id="{{ $secret }}"
                                name="{{ $secret }}"
                                maxlength="{{ $secretMaxLength }}"
                                autocomplete="new-password"
                                @if ($secret !== 'activation_code') inputmode="numeric" @endif
                                placeholder="{{ ($item->id && $item->{$secret}) ? trans('admin/sims/general.secret_is_set') : '' }}"
                                aria-label="{{ trans('admin/sims/table.'.$secret) }}"
                            >
                            @if ($item->id && $item->{$secret})
                                <label class="form-control" style="border: none; padding-left: 0;">
                                    <input type="checkbox" name="clear_{{ $secret }}" value="1" aria-label="clear_{{ $secret }}">
                                    {{ trans('admin/sims/general.clear_secret') }}
                                </label>
                            @endif
                        </x-slot:input>
                    </x-form.row>
                @endforeach
            </fieldset>

            {{-- ============ 5. Custom fields of the plan ============ --}}
            <div id="sim_custom_fields">
                @include('sims/partials/custom-fields', ['item' => $item])
            </div>

            <x-slot:customfooter>
                <x-redirect_submit_options
                    index_route="sims.index"
                    :button_label="trans('general.save')"
                    :options="[
                        'back' => trans('admin/hardware/form.redirect_to_type', ['type' => trans('general.previous_page')]),
                        'index' => trans('admin/hardware/form.redirect_to_all', ['type' => trans('admin/sims/general.sims')]),
                        'item' => trans('admin/hardware/form.redirect_to_type', ['type' => trans('admin/sims/general.sim')]),
                    ]"
                />
            </x-slot:customfooter>

        </x-box>

    </x-form>

</x-container>

@stop

@section('moar_scripts')
<script nonce="{{ csrf_token() }}">
    // Swap in the custom fields of the selected plan, keeping whatever the
    // user already typed into fields that exist in both fieldsets.
    $(function () {
        $('#sim_plan_id_select').on('change', function () {
            var previous = {};
            $.each($('#sim_custom_fields').find('input,select,textarea').serializeArray(), function (i, field) {
                previous[field.name] = field.value;
            });

            $.ajax({
                type: 'GET',
                url: "{{ route('sims.custom-fields') }}",
                data: {
                    sim_plan_id: $(this).val() || '',
                    sim_id: "{{ $item->id }}"
                },
                headers: {"X-Requested-With": 'XMLHttpRequest'},
                dataType: 'html',
                success: function (html) {
                    var $container = $('#sim_custom_fields');
                    $container.html(html);
                    $container.find('select').select2();
                    window.snipeitInitDatetimepickers('#sim_custom_fields');
                    if (window.snipeitInitMacAddressMask) {
                        window.snipeitInitMacAddressMask('#sim_custom_fields');
                    }
                    $container.find('input[type=text],select,textarea').each(function () {
                        if (previous[this.name] !== undefined && $(this).val() === '') {
                            $(this).val(previous[this.name]).trigger('change');
                        }
                    });
                }
            });
        });
    });
</script>
@stop
