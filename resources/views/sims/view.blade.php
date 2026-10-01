@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/sims/general.sim') }}: {{ $sim->display_name }}
    @parent
@stop

@section('header_right')
    <x-button.info-panel-toggle/>
@endsection

@php
    $canViewSecrets = Gate::allows('viewSecrets', $sim);
    $target = $sim->assignedTo;
@endphp

@section('content')
<x-container columns="2">
    <x-page-column class="col-md-9 main-panel">

        {{-- The SIM's number is a user's AD phone/mobile: suggest linking. --}}
        @if (isset($phoneMatches) && $phoneMatches->isNotEmpty())
            <x-callout type="info" role="status">
                <x-icon type="phone" class="fa-fw" />
                {{ trans('admin/sims/general.phone_match_sim', ['number' => $sim->formatted_msisdn]) }}
                <ul style="margin: 6px 0 0 0;">
                    @foreach ($phoneMatches as $matchedUser)
                        <li>
                            {!! $matchedUser->present()->nameUrl() !!}
                            <span class="text-muted">({{ trans('admin/sims/general.match_field_'.$matchedUser->sim_match_field) }})</span>
                            @can('checkout', $sim)
                                @if ($sim->availableForCheckout())
                                    <a href="{{ route('sims.checkout.create', ['sim' => $sim->id, 'assigned_user' => $matchedUser->id]) }}" class="btn btn-xs bg-maroon" style="margin-left: 6px;">
                                        <x-icon type="checkout" class="fa-fw" />
                                        {{ trans('admin/sims/general.link_to_user') }}
                                    </a>
                                @elseif ($sim->assigned_to)
                                    <span class="text-muted">— {{ trans('admin/sims/general.assigned_elsewhere') }}</span>
                                @endif
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </x-callout>
        @endif

        <x-tabs>
            <x-slot:tabnav>
                <x-tabs.details-tab/>
                <x-tabs.files-tab :item="$sim" count="{{ $sim->uploads()->count() }}"/>
                <x-tabs.history-tab count="{{ $sim->history()->count() }}" :model="$sim"/>
                <x-tabs.upload-tab :item="$sim"/>
            </x-slot:tabnav>

            <x-slot:tabpanes>

                <x-tabs.pane name="details">
                    <div class="table-responsive">
                        <table class="table table-striped" style="margin-top: 10px;">
                            <tbody>
                                <tr>
                                    <th class="col-md-3">{{ trans('admin/sims/table.iccid') }}</th>
                                    <td>
                                        <x-copy-to-clipboard copy_what="iccid">{{ $sim->iccid }}</x-copy-to-clipboard>
                                    </td>
                                </tr>
                                @if ($sim->msisdn)
                                    <tr>
                                        <th>{{ trans('admin/sims/table.msisdn') }}</th>
                                        <td><a href="tel:{{ $sim->msisdn }}">{{ $sim->formatted_msisdn }}</a> <span class="text-muted">({{ $sim->phone_country }})</span></td>
                                    </tr>
                                @endif
                                @if ($sim->imsi)
                                    <tr>
                                        <th>{{ trans('admin/sims/table.imsi') }}</th>
                                        <td><code>{{ $sim->imsi }}</code></td>
                                    </tr>
                                @endif
                                <tr>
                                    <th>{{ trans('admin/sims/table.sim_type') }}</th>
                                    <td>{{ trans('admin/sims/general.type_'.$sim->sim_type) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ trans('admin/hardware/form.checkedout_to') }}</th>
                                    <td>
                                        @if ($target)
                                            @if ($sim->checkedOutToUser())
                                                <x-icon type="user" class="fa-fw" />
                                            @elseif ($sim->checkedOutToAsset())
                                                <x-icon type="assets" class="fa-fw" />
                                            @else
                                                <x-icon type="location" class="fa-fw" />
                                            @endif
                                            @if ($target->deleted_at)
                                                <del>{{ $target->display_name }}</del>
                                            @else
                                                {!! $target->present()->nameUrl() !!}
                                            @endif
                                            @if ($sim->last_checkout)
                                                <span class="text-muted">({{ Helper::getFormattedDateObject($sim->last_checkout, 'datetime', false) }})</span>
                                            @endif
                                        @else
                                            <span class="text-muted">{{ trans('admin/sims/general.in_stock') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @if ($sim->expected_checkin)
                                    <tr>
                                        <th>{{ trans('admin/hardware/form.expected_checkin') }}</th>
                                        <td>{{ Helper::getFormattedDateObject($sim->expected_checkin, 'date', false) }}</td>
                                    </tr>
                                @endif
                                @if ($sim->department)
                                    <tr>
                                        <th>{{ trans('general.department') }}</th>
                                        <td>
                                            @can('view', $sim->department)
                                                <a href="{{ route('departments.show', $sim->department) }}">{{ $sim->department->name }}</a>
                                            @else
                                                {{ $sim->department->name }}
                                            @endcan
                                        </td>
                                    </tr>
                                @endif
                                @if ($sim->supplier)
                                    <tr>
                                        <th>{{ trans('admin/sims/table.operator') }}</th>
                                        <td>{!! $sim->supplier->present()->nameUrl() !!}</td>
                                    </tr>
                                @endif
                                @if ($sim->simPlan)
                                    <tr>
                                        <th>{{ trans('admin/sims/table.plan') }}</th>
                                        <td>{!! $sim->simPlan->present()->nameUrl() !!}</td>
                                    </tr>
                                @endif
                                @if ($sim->account_number)
                                    <tr>
                                        <th>{{ trans('admin/sims/table.account_number') }}</th>
                                        <td>{{ $sim->account_number }}</td>
                                    </tr>
                                @endif
                                @if ($sim->monthly_cost !== null)
                                    <tr>
                                        <th>{{ trans('admin/sims/table.monthly_cost') }}</th>
                                        <td>{{ $sim->location->currency ?? $snipeSettings->default_currency }} {{ Helper::formatCurrencyOutput($sim->monthly_cost) }}</td>
                                    </tr>
                                @endif
                                @if ($sim->activation_date)
                                    <tr>
                                        <th>{{ trans('admin/sims/table.activation_date') }}</th>
                                        <td>{{ Helper::getFormattedDateObject($sim->activation_date, 'date', false) }}</td>
                                    </tr>
                                @endif
                                @if ($sim->expiration_date)
                                    <tr>
                                        <th>{{ trans('admin/sims/table.expiration_date') }}</th>
                                        <td>
                                            {{ Helper::getFormattedDateObject($sim->expiration_date, 'date', false) }}
                                            @if ($sim->expiration_date->isPast())
                                                <span class="label label-danger">{{ trans('admin/sims/general.expired') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endif

                                {{-- Secrets: only rendered at all for users with sims.secrets. --}}
                                @if ($canViewSecrets)
                                    @foreach (\App\Models\Sim::SECRET_FIELDS as $secret)
                                        @if ($sim->{$secret})
                                            <tr>
                                                <th><x-icon type="locked" class="fa-fw" /> {{ trans('admin/sims/table.'.$secret) }}</th>
                                                <td>
                                                    <span class="js-sim-secret" data-secret="{{ $sim->{$secret} }}">{{ \App\Models\Sim::SECRET_MASK }}</span>
                                                    <button type="button" class="btn btn-xs btn-default js-sim-secret-toggle" aria-label="{{ trans('admin/sims/general.show_secret') }}">
                                                        {{ trans('admin/sims/general.show_secret') }}
                                                    </button>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                @endif

                                @foreach ($sim->customFields() as $field)
                                    @php $fieldValue = $sim->rawCustomFieldValue($field); @endphp
                                    @if (($fieldValue !== null) && ($fieldValue !== ''))
                                        <tr>
                                            <th>
                                                @if ($field->field_encrypted)
                                                    <x-icon type="locked" class="fa-fw" />
                                                @endif
                                                {{ $field->name }}
                                            </th>
                                            <td>
                                                @if (($field->field_encrypted == '1') && ! $canViewSecrets)
                                                    {{ strtoupper(trans('admin/custom_fields/general.encrypted')) }}
                                                @elseif ($field->element == 'markdown-textarea')
                                                    {!! Helper::renderMarkdown($sim->customFieldValue($field)) !!}
                                                @elseif (($field->format == 'URL') && preg_match('#^https?://#i', (string) $sim->customFieldValue($field)))
                                                    {{-- Only http(s) becomes a link: never javascript:/data: URLs. --}}
                                                    <a href="{{ $sim->customFieldValue($field) }}" target="_blank" rel="noopener noreferrer">{{ $sim->customFieldValue($field) }}</a>
                                                @else
                                                    {!! nl2br(e($sim->customFieldValue($field))) !!}
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach

                                @if ($sim->notes)
                                    <tr>
                                        <th>{{ trans('general.notes') }}</th>
                                        <td>{!! nl2br(Helper::parseEscapedMarkedownInline($sim->notes)) !!}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </x-tabs.pane>

                <x-tabs.pane name="files">
                    <x-table.files object_type="sims" :object="$sim"/>
                </x-tabs.pane>

                <x-tabs.pane name="history">
                    <x-table.history :model="$sim" :route="route('api.sims.history', $sim)"/>
                </x-tabs.pane>

            </x-slot:tabpanes>
        </x-tabs>
    </x-page-column>

    <x-page-column class="col-md-3">
        <x-box class="side-box expanded">
            <x-info-panel :infoPanelObj="$sim" :qr_code_url="route('qr_code/common', ['object_type' => 'sims', 'id' => $sim->id])">
                <x-slot:buttons>
                    <x-button.edit :item="$sim" :route="route('sims.edit', $sim->id)"/>
                    <x-button.clone :item="$sim" :route="route('sims.clone.create', $sim->id)"/>
                    @if ($sim->assigned_to)
                        <x-button.checkin :item="$sim" :route="route('sims.checkin.create', $sim->id)"/>
                    @else
                        <x-button.checkout :item="$sim" :route="route('sims.checkout.create', $sim->id)"/>
                    @endif
                    <x-button.delete :item="$sim"/>
                </x-slot:buttons>
            </x-info-panel>
        </x-box>
    </x-page-column>
</x-container>
@endsection

@section('moar_scripts')
    @can('files', $sim)
        @include ('modals.upload-file', ['item_type' => 'sims', 'item_id' => $sim->id])
    @endcan

    @if ($canViewSecrets)
        <script nonce="{{ csrf_token() }}">
            $(function () {
                $('.js-sim-secret-toggle').on('click', function () {
                    var $value = $(this).siblings('.js-sim-secret');
                    var revealed = $value.data('revealed') === true;
                    $value.text(revealed ? '{{ \App\Models\Sim::SECRET_MASK }}' : $value.data('secret'));
                    $value.data('revealed', ! revealed);
                    $(this).text(revealed ? '{{ trans('admin/sims/general.show_secret') }}' : '{{ trans('admin/sims/general.hide_secret') }}');
                });
            });
        </script>
    @endif

    @include ('partials.bootstrap-table', ['exportFile' => 'sim-'.$sim->id.'-export', 'search' => false])
@endsection
