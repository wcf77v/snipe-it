@component('mail::message')
# {{ trans('mail.hello') }} {{ $recipientName }},

{{ trans('admin/sims/mail.checkout_intro') }}

@component('mail::table')
|        |          |
| ------------- | ------------- |
@if ($item->msisdn)
| **{{ trans('admin/sims/table.msisdn') }}** | {{ $item->formatted_msisdn }} |
@endif
@if ($item->iccid)
| **{{ trans('admin/sims/table.iccid') }}** | {{ $item->iccid }} |
@endif
@if ($item->supplier)
| **{{ trans('admin/sims/table.operator') }}** | {{ $item->supplier->name }} |
@endif
@if ($item->simPlan)
| **{{ trans('admin/sims/table.plan') }}** | {{ $item->simPlan->name }} |
@endif
@if ($item->expected_checkin)
| **{{ trans('admin/hardware/form.expected_checkin') }}** | {{ Helper::getFormattedDateObject($item->expected_checkin, 'date', false) }} |
@endif
@if ($note)
| **{{ trans('mail.additional_notes') }}** | {{ $note }} |
@endif
@if ($admin)
| **{{ trans('general.administrator') }}** | {{ $admin->display_name }} |
@endif
@endcomponent

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
