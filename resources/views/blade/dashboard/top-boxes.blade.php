@props(['counts'])

{{-- Admin-only inventory summary row. Trying to determine the box sizes
for an unpredictable number of boxes (logged in user can only see accessories,
nothing else, etc makes for a really awkward display view --}}
@isset($counts['sim'])
    {{-- Seven boxes with SIM cards: keep them on one row on large screens. --}}
    <style nonce="{{ csrf_token() }}">
        @media (min-width: 1200px) {
            .dashboard-top-boxes > .col-lg-2 { width: 14.2857%; }
        }
    </style>
@endisset
<div class="row dashboard-top-boxes">

    <div class="col-lg-2 col-xs-6">
        <a href="{{ route('hardware.index') }}">
            <div class="dashboard small-box bg-teal">
                <div class="inner">
                    <h3>{{ number_format(\App\Models\Asset::AssetsForShow()->count()) }}</h3>
                    <p>{{ trans('general.assets') }}</p>
                </div>
                <div class="icon" aria-hidden="true">
                    <x-icon type="assets"/>
                </div>
                <span class="small-box-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right"/>
                </span>
            </div>
        </a>
    </div>

    <div class="col-lg-2 col-xs-6">
        <a href="{{ route('licenses.index') }}" aria-hidden="true">
            <div class="dashboard small-box bg-maroon">
                <div class="inner">
                    <h3>{{ number_format($counts['license']) }}</h3>
                    <p>{{ trans('general.licenses') }}</p>
                </div>
                <div class="icon" aria-hidden="true">
                    <x-icon type="licenses"/>
                </div>
                <span class="small-box-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right"/>
                </span>
            </div>
        </a>
    </div>

    <div class="col-lg-2 col-xs-6">
        <a href="{{ route('accessories.index') }}">
            <div class="dashboard small-box bg-orange">
                <div class="inner">
                    <h3>{{ number_format($counts['accessory']) }}</h3>
                    <p>{{ trans('general.accessories') }}</p>
                </div>
                <div class="icon" aria-hidden="true">
                    <x-icon type="accessories"/>
                </div>
                <span class="small-box-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right"/>
                </span>
            </div>
        </a>
    </div>

    <div class="col-lg-2 col-xs-6">
        <a href="{{ route('consumables.index') }}">
            <div class="dashboard small-box bg-purple">
                <div class="inner">
                    <h3>{{ number_format($counts['consumable']) }}</h3>
                    <p>{{ trans('general.consumables') }}</p>
                </div>
                <div class="icon" aria-hidden="true">
                    <x-icon type="consumables"/>
                </div>
                <span class="small-box-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right"/>
                </span>
            </div>
        </a>
    </div>

    <div class="col-lg-2 col-xs-6">
        <a href="{{ route('components.index') }}">
            <div class="dashboard small-box bg-yellow">
                <div class="inner">
                    <h3>{{ number_format($counts['component']) }}</h3>
                    <p>{{ trans('general.components') }}</p>
                </div>
                <div class="icon" aria-hidden="true">
                    <x-icon type="components"/>
                </div>
                <span class="small-box-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right"/>
                </span>
            </div>
        </a>
    </div>

    @isset($counts['sim'])
    <div class="col-lg-2 col-xs-6">
        <a href="{{ route('sims.index') }}">
            <div class="dashboard small-box bg-olive">
                <div class="inner">
                    <h3>{{ number_format($counts['sim']) }}</h3>
                    <p>{{ trans('admin/sims/general.sims') }}</p>
                </div>
                <div class="icon" aria-hidden="true">
                    <x-icon type="sims"/>
                </div>
                <span class="small-box-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right"/>
                </span>
            </div>
        </a>
    </div>
    @endisset

    <div class="col-lg-2 col-xs-6">
        <a href="{{ route('users.index') }}">
            <div class="dashboard small-box bg-light-blue">
                <div class="inner">
                    <h3>{{ number_format($counts['user']) }}</h3>
                    <p>{{ trans('general.people') }}</p>
                </div>
                <div class="icon" aria-hidden="true">
                    <x-icon type="users"/>
                </div>
                <span class="small-box-footer">
                    {{ trans('general.view_all') }}
                    <x-icon type="arrow-circle-right"/>
                </span>
            </div>
        </a>
    </div>

</div>
