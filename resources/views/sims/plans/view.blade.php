@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/sims/table.plan') }}: {{ $plan->name }}
    @parent
@stop

@section('header_right')
    @can('update', $plan)
        <a href="{{ route('simplans.edit', $plan) }}" class="btn btn-warning pull-right">
            <x-icon type="edit" class="fa-fw" />
            {{ trans('general.update') }}
        </a>
    @endcan
    @can('delete', $plan)
        @if ($plan->isDeletable())
            {{-- Shared confirm modal (snipeit.js .delete-asset): the message is
                 set as text, never evaluated, and the form carries CSRF + DELETE. --}}
            <button type="button" class="btn btn-danger pull-right delete-asset" style="margin-right: 5px;"
                    data-toggle="modal" data-target="#dataConfirmModal" data-icon="fa-trash"
                    data-href="{{ route('simplans.destroy', $plan) }}"
                    data-title="{{ trans('general.delete') }}"
                    data-content="{{ trans('general.sure_to_delete_var', ['item' => $plan->name]) }}">
                <x-icon type="delete" class="fa-fw" />
                {{ trans('general.delete') }}
            </button>
        @endif
    @endcan
@stop

@section('content')
<x-container>
    <x-box>
        <table class="table table-striped">
            <tbody>
                <tr>
                    <th class="col-md-3">{{ trans('admin/sims/table.operator') }}</th>
                    <td>{!! $plan->supplier ? $plan->supplier->present()->nameUrl() : '—' !!}</td>
                </tr>
                <tr>
                    <th>{{ trans('admin/sims/general.fieldset') }}</th>
                    <td>
                        @if ($plan->fieldset)
                            <a href="{{ route('fieldsets.show', $plan->fieldset) }}">{{ $plan->fieldset->name }}</a>
                            <span class="text-muted">({{ $plan->fieldset->fields->pluck('name')->implode(', ') }})</span>
                        @else
                            <span class="text-muted">{{ trans('admin/sims/general.use_default_fieldset') }}</span>
                        @endif
                    </td>
                </tr>
                @if ($plan->monthly_cost !== null)
                    <tr>
                        <th>{{ trans('admin/sims/table.monthly_cost') }}</th>
                        <td>{{ $snipeSettings->default_currency }} {{ Helper::formatCurrencyOutput($plan->monthly_cost) }}</td>
                    </tr>
                @endif
                @if ($plan->company)
                    <tr>
                        <th>{{ trans('general.company') }}</th>
                        <td>{{ $plan->company->name }}</td>
                    </tr>
                @endif
                @if ($plan->notes)
                    <tr>
                        <th>{{ trans('general.notes') }}</th>
                        <td>{!! nl2br(Helper::parseEscapedMarkedownInline($plan->notes)) !!}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </x-box>

    <x-box>
        <x-table.sims name="planSims" :route="route('api.sims.index', ['sim_plan_id' => $plan->id])" />
    </x-box>
</x-container>
@stop

@section('moar_scripts')
    @include ('partials.bootstrap-table', ['exportFile' => 'sim-plan-'.$plan->id.'-export', 'search' => true])
@stop
