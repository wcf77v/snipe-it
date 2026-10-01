@component('mail::message')
# {{ trans('mail.hello') }} {{ $recipientName }},

{{ trans('admin/sims/mail.checkin_intro') }}

@component('mail::table')
|        |          |
| ------------- | ------------- |
@if ($item->msisdn)
| **{{ trans('admin/sims/table.msisdn') }}** | {{ $item->formatted_msisdn }} |
@endif
@if ($item->iccid)
| **{{ trans('admin/sims/table.iccid') }}** | {{ $item->iccid }} |
@endif
@if ($admin)
| **{{ trans('general.administrator') }}** | {{ $admin->display_name }} |
@endif
@if ($note)
| **{{ trans('mail.additional_notes') }}** | {{ $note }} |
@endif
@endcomponent

{{ trans('mail.best_regards') }}

{{ $snipeSettings->site_name }}

@endcomponent
