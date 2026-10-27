@props([
    'count' => null,
    'class' => false,
])

@can('view', \App\Models\Sim::class)
    <x-tabs.nav-item
            :$class
            name="sims"
            icon_type="sims"
            label="{{ trans('admin/sims/general.sims') }}"
            count="{{ $count }}"
            tooltip="{{ trans('admin/sims/general.sims') }}"
    />
@endcan
