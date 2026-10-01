@props([
    'route' => route('api.sims.index'),
    'name' => 'sims',
    'presenter' => \App\Presenters\SimPresenter::dataTableLayout(),
    'fixed_right_number' => 2,
    'fixed_number' => 1,
    'table_header' => trans('admin/sims/general.sims'),
    'buttons' => null,
])

<!-- start sims table -->
@can('view', \App\Models\Sim::class)

    <x-slot:table_header>
        {{ $table_header }}
    </x-slot:table_header>

    <x-table
        :$presenter
        :$fixed_right_number
        :$fixed_number
        :$buttons
        show_column_search="true"
        show_advanced_search="true"
        sort_field="created_at"
        sort_order="desc"
        api_url="{{ $route }}"
        export_filename="export-{{ str_slug($name) }}-sims-{{ date('Y-m-d') }}"
    />

@endcan
<!-- end sims table -->
