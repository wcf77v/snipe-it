@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/sims/general.matches') }}
    @parent
@stop

{{-- Page content --}}
@section('content')
<x-container>
    <x-box>
        <x-slot:header>{{ trans('admin/sims/general.matches') }}</x-slot:header>

        <p class="help-block">{{ trans('admin/sims/general.matches_help') }}</p>

        @if (empty($fields))
            <x-callout type="warning">
                {{ trans('admin/sims/general.matching_disabled') }}
                @can('manageSettings', \App\Models\Sim::class)
                    <a href="{{ route('sims.settings.edit') }}">{{ trans('admin/sims/general.settings') }}</a>
                @endcan
            </x-callout>
        @elseif ($matches->isEmpty())
            <x-callout type="success" role="status">{{ trans('admin/sims/general.no_matches') }}</x-callout>
        @else
            <form method="POST" action="{{ route('sims.matches.store') }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 30px;">
                                    <input type="checkbox" class="js-sim-match-all" aria-label="{{ trans('general.select_all_none') }}">
                                </th>
                                <th>{{ trans('admin/sims/general.sim') }}</th>
                                <th>{{ trans('admin/sims/table.msisdn') }}</th>
                                <th>{{ trans('general.user') }}</th>
                                <th>{{ trans('admin/sims/general.matched_field') }}</th>
                                <th>{{ trans('general.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($matches as $match)
                                @php $available = $match['status'] === 'available' && $match['sim']->availableForCheckout(); @endphp
                                <tr>
                                    <td>
                                        @can('checkout', $match['sim'])
                                            <input type="checkbox" name="matches[]" value="{{ $match['sim']->id }}:{{ $match['user']->id }}" class="js-sim-match" @disabled(! $available) aria-label="{{ $match['sim']->display_name }}">
                                        @endcan
                                    </td>
                                    <td>{!! $match['sim']->present()->nameUrl() !!}<br><small class="text-muted">{{ $match['sim']->iccid }}</small></td>
                                    <td>{{ $match['sim']->formatted_msisdn }}</td>
                                    <td>{!! $match['user']->present()->nameUrl() !!}</td>
                                    <td>{{ trans('admin/sims/general.match_field_'.$match['field']) }}</td>
                                    <td>
                                        @if ($available)
                                            <span class="label label-success">{{ trans('admin/sims/general.in_stock') }}</span>
                                        @elseif ($match['sim']->assignedTo)
                                            <span class="label label-warning">{{ trans('admin/sims/general.assigned_elsewhere') }}</span>
                                            {{ $match['sim']->assignedTo->display_name }}
                                        @else
                                            <span class="label label-default">{{ trans('admin/sims/general.not_deployable') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @can('checkout', \App\Models\Sim::class)
                    <div class="form-group">
                        <label for="note">{{ trans('general.notes') }}</label>
                        <textarea class="form-control" id="note" name="note" rows="2" @required($snipeSettings->require_checkinout_notes)>{{ old('note') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <x-icon type="checkout" class="fa-fw" />
                        {{ trans('admin/sims/general.link_selected') }}
                    </button>
                @endcan
            </form>
        @endif
    </x-box>
</x-container>
@stop

@section('moar_scripts')
<script nonce="{{ csrf_token() }}">
    $(function () {
        $('.js-sim-match-all').on('change', function () {
            $('.js-sim-match:not(:disabled)').prop('checked', $(this).is(':checked'));
        });
    });
</script>
@stop
