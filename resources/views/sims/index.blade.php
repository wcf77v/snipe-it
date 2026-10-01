@extends('layouts/default')

{{-- Page title --}}
@section('title')
{{ trans('admin/sims/general.sims') }}
@parent
@stop

@section('header_right')
    @can('manageSettings', \App\Models\Sim::class)
        <a href="{{ route('sims.settings.edit') }}" class="btn btn-default pull-right" style="margin-left: 5px;">
            <x-icon type="settings" class="fa-fw" />
            {{ trans('admin/sims/general.settings') }}
        </a>
    @endcan
    @can('create', \App\Models\Sim::class)
        <a href="{{ route('sims.import.create') }}" class="btn btn-default pull-right" style="margin-left: 5px;">
            <x-icon type="import" class="fa-fw" />
            {{ trans('admin/sims/import.title') }}
        </a>
        <a href="{{ route('sims.create') }}" class="btn btn-primary pull-right" {{ $snipeSettings->shortcuts_enabled == 1 ? 'accesskey=n' : '' }}>
            <x-icon type="plus" class="fa-fw" />
            {{ trans('general.create') }}
        </a>
    @endcan
@stop

{{-- Page content --}}
@section('content')
    <x-container>
        <x-box>
            <x-table.sims :route="route('api.sims.index', request()->only(['status', 'status_type', 'supplier_id', 'location_id', 'department_id', 'company_id', 'status_id', 'sim_type', 'sim_plan_id']))" />
        </x-box>
    </x-container>
@stop

@section('moar_scripts')
    @include ('partials.bootstrap-table', ['exportFile' => 'sims-export', 'search' => true, 'showFooter' => true, 'columns' => \App\Presenters\SimPresenter::dataTableLayout()])
@stop
