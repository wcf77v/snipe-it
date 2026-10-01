@extends('layouts/default')

{{-- Page title --}}
@section('title')
    @if ($item->id)
        {{ trans('admin/sims/general.plan_update') }}
    @else
        {{ trans('admin/sims/general.plan_create') }}
    @endif
    @parent
@stop

{{-- Page content --}}
@section('content')

<x-container class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1 col-sm-12 col-sm-offset-0">

    <x-form :$item route="{{ $item->id ? route('simplans.update', $item->id) : route('simplans.store') }}">

        <x-box top_submit>
            @if ($item->id)
                <x-slot:header>{{ $item->name }}</x-slot:header>
            @endif

            <x-input.company-select
                :label="trans('general.company')"
                name="company_id"
                :selected="old('company_id', $item->company_id)"
            />

            <x-form.row
                :label="trans('general.name')"
                :$item
                name="name"
            />

            <x-input.supplier-select
                :label="trans('admin/sims/table.operator')"
                name="supplier_id"
                :selected="old('supplier_id', $item->supplier_id)"
            />

            <x-form.row :label="trans('admin/sims/general.fieldset')" name="fieldset_id" input_div_class="col-md-7"
                :help_text="trans('admin/sims/general.plan_fieldset_help')">
                <x-slot:input>
                    <x-input.select
                        name="fieldset_id"
                        id="fieldset_id"
                        :options="['' => trans('admin/sims/general.use_default_fieldset')] + $fieldsets->all()"
                        :selected="old('fieldset_id', $item->fieldset_id)"
                        style="width: 100%;"
                        aria-label="fieldset_id"
                    />
                </x-slot:input>
            </x-form.row>

            <x-form.row
                :label="trans('admin/sims/table.monthly_cost')"
                :$item
                name="monthly_cost"
                type="number"
                min="0"
                input_div_class="col-md-4"
                :input_group_addon="$snipeSettings->default_currency"
            />

            <x-form.row
                :label="trans('general.notes')"
                :$item
                name="notes"
                type="textarea"
            />

        </x-box>

    </x-form>

</x-container>

@stop
