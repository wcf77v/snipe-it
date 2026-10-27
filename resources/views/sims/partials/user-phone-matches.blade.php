{{--
    SIM cards whose number equals this user's phone/mobile (from AD) but
    that are not checked out to the user yet. Linking goes through the
    normal checkout form, pre-filled with the user.
--}}
@php
    $userSimMatches = \App\Services\SimPhoneMatcher::simsForUser($user)
        ->reject(fn ($sim) => $sim->checkedOutToUser() && ((int) $sim->assigned_to === (int) $user->id));
@endphp

@if ($userSimMatches->isNotEmpty())
    <div class="clearfix" style="margin-bottom: 10px;">
        <x-callout type="info" role="status">
            <x-icon type="phone" class="fa-fw" />
            {{ trans('admin/sims/general.phone_match_user') }}
            <ul style="margin: 6px 0 0 0;">
                @foreach ($userSimMatches as $matchedSim)
                    <li>
                        {!! $matchedSim->present()->nameUrl() !!}
                        <span class="text-muted">{{ $matchedSim->formatted_msisdn }} ({{ trans('admin/sims/general.match_field_'.$matchedSim->sim_match_field) }})</span>
                        @can('checkout', $matchedSim)
                            @if ($matchedSim->availableForCheckout())
                                <a href="{{ route('sims.checkout.create', ['sim' => $matchedSim->id, 'assigned_user' => $user->id]) }}" class="btn btn-xs bg-maroon" style="margin-left: 6px;">
                                    <x-icon type="checkout" class="fa-fw" />
                                    {{ trans('admin/sims/general.link_to_user') }}
                                </a>
                            @elseif ($matchedSim->assignedTo)
                                <span class="text-muted">— {{ trans('admin/sims/general.assigned_elsewhere') }}: {{ $matchedSim->assignedTo->display_name }}</span>
                            @endif
                        @endcan
                    </li>
                @endforeach
            </ul>
        </x-callout>
    </div>
@endif
