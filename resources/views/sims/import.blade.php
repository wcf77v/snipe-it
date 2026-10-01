@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/sims/import.title') }}
    @parent
@stop

@section('content')
<x-container class="col-lg-10 col-lg-offset-1 col-md-12">

    <x-box>
        <x-slot:header>{{ trans('admin/sims/import.title') }}</x-slot:header>

        <p>{{ trans('admin/sims/import.help') }}</p>
        <p class="help-block">
            {{ trans('admin/sims/import.columns') }}:
            @foreach ($columns as $column)
                <code>{{ $column }}</code>@if (! $loop->last), @endif
            @endforeach
        </p>
        <p class="help-block">{{ trans('admin/sims/import.matching_help') }}</p>
        <p>
            <a href="{{ route('sims.import.template') }}" class="btn btn-default btn-sm">
                <x-icon type="download" class="fa-fw" />
                {{ trans('admin/sims/import.template') }}
            </a>
        </p>

        <form method="POST" action="{{ route('sims.import.store') }}" enctype="multipart/form-data" class="form-horizontal">
            @csrf

            <div class="form-group{{ $errors->has('file') ? ' has-error' : '' }}">
                <label for="file" class="col-md-3 control-label">{{ trans('admin/sims/import.file') }}</label>
                <div class="col-md-7">
                    <input type="file" name="file" id="file" accept=".csv,text/csv" class="form-control" required>
                    <x-form.error name="file" />
                </div>
            </div>

            <div class="form-group">
                <div class="col-md-7 col-md-offset-3">
                    <label class="form-control">
                        <input type="checkbox" name="create_missing" value="1" @checked(old('create_missing'))>
                        <span>{{ trans('admin/sims/import.create_missing') }}</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <div class="col-md-7 col-md-offset-3">
                    <button type="submit" name="mode" value="check" class="btn btn-default">
                        <x-icon type="search" class="fa-fw" />
                        {{ trans('admin/sims/import.check') }}
                    </button>
                    <button type="submit" name="mode" value="import" class="btn btn-primary">
                        <x-icon type="import" class="fa-fw" />
                        {{ trans('admin/sims/import.import') }}
                    </button>
                    <p class="help-block">{{ trans('admin/sims/import.check_help') }}</p>
                </div>
            </div>
        </form>
    </x-box>

    @if ($result)
        <x-box>
            <x-slot:header>
                {{ $result['dry_run'] ? trans('admin/sims/import.result_check') : trans('admin/sims/import.result_import') }}
            </x-slot:header>

            <p>
                <span class="label label-success">{{ trans('admin/sims/import.created') }}: {{ $result['created'] }}</span>
                <span class="label label-info">{{ trans('admin/sims/import.updated') }}: {{ $result['updated'] }}</span>
                <span class="label label-danger">{{ trans('admin/sims/import.failed') }}: {{ $result['failed'] }}</span>
            </p>

            @if ($result['dry_run'] && ($result['created'] + $result['updated']) > 0)
                <x-callout type="info" role="status">{{ trans('admin/sims/import.dry_run_note') }}</x-callout>
            @endif

            <div class="table-responsive">
                <table class="table table-striped table-condensed">
                    <thead>
                        <tr>
                            <th style="width: 80px;">{{ trans('admin/sims/import.line') }}</th>
                            <th style="width: 120px;">{{ trans('general.status') }}</th>
                            <th>{{ trans('admin/sims/general.sim') }}</th>
                            <th>{{ trans('general.notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($result['rows'] as $row)
                            <tr>
                                <td>{{ $row['line'] }}</td>
                                <td>
                                    @if ($row['status'] === 'created')
                                        <span class="label label-success">{{ trans('admin/sims/import.created') }}</span>
                                    @elseif ($row['status'] === 'updated')
                                        <span class="label label-info">{{ trans('admin/sims/import.updated') }}</span>
                                    @else
                                        <span class="label label-danger">{{ trans('admin/sims/import.failed') }}</span>
                                    @endif
                                </td>
                                <td>{{ $row['sim'] }}</td>
                                <td>{{ implode(' ', $row['messages']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">{{ trans('admin/sims/import.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-box>
    @endif

</x-container>
@stop
