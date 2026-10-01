@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('admin/sims/general.plans') }}
@parent
@stop

@section('header_right')
    @can('create', \App\Models\SimPlan::class)
        <a href="{{ route('simplans.create') }}" class="btn btn-primary pull-right">
            <x-icon type="plus" class="fa-fw" />
            {{ trans('general.create') }}
        </a>
    @endcan
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box>
            <p class="help-block">{{ trans('admin/sims/general.plans_help') }}</p>
            <x-table
                name="simplans"
                :presenter="\App\Presenters\SimPlanPresenter::dataTableLayout()"
                show_column_search="true"
                api_url="{{ route('api.simplans.index') }}"
                export_filename="export-sim-plans-{{ date('Y-m-d') }}"
            />
        </x-box>
    </x-container>
@stop

@section('moar_scripts')
    @include ('partials.bootstrap-table', ['exportFile' => 'sim-plans-export', 'search' => true])
@stop
