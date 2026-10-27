<?php

namespace App\Presenters;

/**
 * Class SimPlanPresenter
 */
class SimPlanPresenter extends Presenter
{
    /**
     * Json Column Layout for bootstrap table
     *
     * @return string
     */
    public static function dataTableLayout()
    {
        $layout = [
            [
                'field' => 'id',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.id'),
                'visible' => false,
            ], [
                'field' => 'name',
                'searchable' => true,
                'sortable' => true,
                'switchable' => false,
                'title' => trans('general.name'),
                'visible' => true,
                'formatter' => 'simplansLinkFormatter',
            ], [
                'field' => 'supplier',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/table.operator'),
                'visible' => true,
                'formatter' => 'suppliersLinkObjFormatter',
            ], [
                'field' => 'fieldset',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/general.fieldset'),
                'visible' => true,
                'formatter' => 'fieldsetsLinkObjFormatter',
            ], [
                'field' => 'monthly_cost',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/table.monthly_cost'),
                'visible' => true,
                'class' => 'text-right',
            ], [
                'field' => 'sims_count',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/general.sims'),
                'visible' => true,
                'class' => 'text-right',
            ], [
                'field' => 'company',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.company'),
                'visible' => false,
                'formatter' => 'companiesLinkObjFormatter',
            ], [
                'field' => 'notes',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.notes'),
                'visible' => false,
                'formatter' => 'notesFormatter',
            ], [
                'field' => 'actions',
                'searchable' => false,
                'sortable' => false,
                'switchable' => false,
                'title' => trans('table.actions'),
                'formatter' => 'simplansActionsFormatter',
                'printIgnore' => true,
                'class' => 'hidden-print',
            ],
        ];

        return json_encode($layout);
    }

    public function nameUrl()
    {
        if (auth()->user()->can('view', $this->model)) {
            return '<a href="'.route('simplans.show', $this->id).'">'.e($this->name).'</a>';
        }

        return e($this->name);
    }

    public function viewUrl()
    {
        return route('simplans.show', $this->id);
    }
}
