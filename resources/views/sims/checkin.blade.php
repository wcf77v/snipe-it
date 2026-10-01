@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/sims/general.checkin') }}
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

        <x-form :route="route('sims.checkin.store', $sim)">

            <x-box>
                <x-slot:header>
                    {{ trans('admin/sims/general.sim') }}: {{ $sim->display_name }}
                </x-slot:header>

                <x-form.static :label="trans('admin/sims/table.iccid')">{{ $sim->iccid }}</x-form.static>

                @if ($sim->msisdn)
                    <x-form.static :label="trans('admin/sims/table.msisdn')">{{ $sim->formatted_msisdn }}</x-form.static>
                @endif

                @if ($sim->assignedTo)
                    <x-form.static :label="trans('admin/hardware/form.checkedout_to')">{{ $sim->assignedTo->display_name }}</x-form.static>
                @endif

                <x-form.row
                    :label="trans('admin/hardware/form.checkin_date')"
                    name="checkin_at"
                    type="datetimepicker"
                    :default="date('Y-m-d H:i:s')"
                    input_div_class="col-md-5"
                />

                <x-input.location-select
                    :label="trans('admin/sims/general.return_location')"
                    name="location_id"
                    :selected="old('location_id', $sim->location_id)"
                />

                <x-form.row :label="trans('general.notes')" name="note">
                    <x-slot:input>
                        <textarea class="col-md-6 form-control" id="note" name="note" @required($snipeSettings->require_checkinout_notes)>{{ old('note') }}</textarea>
                    </x-slot:input>
                </x-form.row>

                <x-slot:customfooter>
                    <x-redirect_submit_options
                        index_route="sims.index"
                        :button_label="trans('general.checkin')"
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
