@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/sims/general.checkout') }}
    @parent
@stop

{{-- Page content --}}
@section('content')

    <style>
        .input-group {
            padding-left: 0px !important;
        }
    </style>

    <x-container class="col-md-7 col-sm-11 col-xs-12 col-md-offset-2">

        <x-form id="checkout_form" :route="route('sims.checkout.store', $sim)">

            <x-box>
                <x-slot:header>
                    {{ trans('admin/sims/general.sim') }}: {{ $sim->display_name }}
                </x-slot:header>

                <x-form.static :label="trans('admin/sims/table.iccid')">{{ $sim->iccid }}</x-form.static>

                @if ($sim->msisdn)
                    <x-form.static :label="trans('admin/sims/table.msisdn')">{{ $sim->formatted_msisdn }}</x-form.static>
                @endif

                @if ($sim->supplier)
                    <x-form.static :label="trans('admin/sims/table.operator')">{{ $sim->supplier->name }}</x-form.static>
                @endif

                @if ($sim->company)
                    <x-form.static :label="trans('general.company')">{{ $sim->company->name }}</x-form.static>
                @endif

                {{-- Same target picker as asset checkout; snipeit.js toggles
                     #assigned_user / #assigned_asset / #assigned_location. --}}
                @include ('partials.forms.checkout-selector', ['user_select' => 'true', 'asset_select' => 'true', 'location_select' => 'true'])

                @if ($phoneMatches->isNotEmpty())
                    <div class="form-group">
                    <div class="col-md-9 col-md-offset-3">
                        <x-callout type="info" role="status">
                            <x-icon type="phone" class="fa-fw" />
                            {{ trans('admin/sims/general.phone_match_checkout') }}
                            @foreach ($phoneMatches as $matchedUser)
                                <a href="{{ route('sims.checkout.create', ['sim' => $sim->id, 'assigned_user' => $matchedUser->id]) }}">{{ $matchedUser->display_name }}</a>
                                <span class="text-muted">({{ trans('admin/sims/general.match_field_'.$matchedUser->sim_match_field) }})</span>@if (! $loop->last), @endif
                            @endforeach
                        </x-callout>
                    </div>
                    </div>
                @endif

                <x-input.user-select
                    :label="trans('general.user')"
                    name="assigned_user"
                    :selected="old('assigned_user', $suggestedUserId)"
                    :companyId="$sim->company_id"
                    :style="(session('checkout_to_type') ?: 'user') == 'user' ? null : 'display: none;'"
                />

                @include ('partials.forms.edit.asset-select', ['translated_name' => trans('general.select_asset'), 'fieldname' => 'assigned_asset', 'company_id' => $sim->company_id, 'unselect' => 'true', 'style' => session('checkout_to_type') == 'asset' ? '' : 'display: none;'])

                @include ('partials.forms.edit.location-select', ['translated_name' => trans('general.location'), 'fieldname' => 'assigned_location', 'company_id' => $sim->company_id, 'style' => session('checkout_to_type') == 'location' ? '' : 'display: none;'])

                <x-form.row
                    :label="trans('admin/hardware/form.checkout_date')"
                    name="checkout_at"
                    type="datetimepicker"
                    :default="date('Y-m-d H:i:s')"
                    input_div_class="col-md-5"
                />

                <x-form.row
                    :label="trans('admin/hardware/form.expected_checkin')"
                    name="expected_checkin"
                    type="datepicker"
                    input_div_class="col-md-4"
                />

                <x-form.row :label="trans('general.notes')" name="note">
                    <x-slot:input>
                        <textarea class="col-md-6 form-control" id="note" name="note" @required($snipeSettings->require_checkinout_notes)>{{ old('note') }}</textarea>
                    </x-slot:input>
                </x-form.row>

                <x-slot:customfooter>
                    <x-redirect_submit_options
                        index_route="sims.index"
                        :button_label="trans('general.checkout')"
                        :options="[
                            'index' => trans('admin/hardware/form.redirect_to_all', ['type' => trans('admin/sims/general.sims')]),
                            'item' => trans('admin/hardware/form.redirect_to_type', ['type' => trans('admin/sims/general.sim')]),
                            'target' => trans('admin/hardware/form.redirect_to_checked_out_to'),
                        ]"
                    />
                </x-slot:customfooter>

            </x-box>

        </x-form>

    </x-container>
@stop
