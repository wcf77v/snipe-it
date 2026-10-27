<?php

namespace App\Presenters;

use App\Models\Sim;
use Illuminate\Support\Facades\Gate;

/**
 * Class SimPresenter
 */
class SimPresenter extends Presenter
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
                'switchable' => true,
                'title' => trans('general.name'),
                'visible' => true,
                'formatter' => 'simsLinkFormatter',
            ], [
                'field' => 'iccid',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/table.iccid'),
                'visible' => true,
                'formatter' => 'simsLinkFormatter',
            ], [
                'field' => 'msisdn_formatted',
                'sortName' => 'msisdn',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/table.msisdn'),
                'visible' => true,
                // Link to the SIM (like name/ICCID), not tel: — clicking a
                // number must open the card, not start a phone call.
                'formatter' => 'simsLinkFormatter',
            ], [
                'field' => 'imsi',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/table.imsi'),
                'visible' => false,
            ], [
                'field' => 'sim_type_label',
                'searchable' => false,
                'sortable' => false,
                'switchable' => true,
                'title' => trans('admin/sims/table.sim_type'),
                'visible' => true,
            ], [
                'field' => 'supplier',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/table.operator'),
                'visible' => true,
                'formatter' => 'suppliersLinkObjFormatter',
            ], [
                'field' => 'sim_plan',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/table.plan'),
                'visible' => true,
                'formatter' => 'simplansLinkObjFormatter',
            ], [
                'field' => 'account_number',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/table.account_number'),
                'visible' => false,
            ], [
                'field' => 'monthly_cost',
                'searchable' => false,
                'sortable' => false,
                'switchable' => true,
                'title' => trans('admin/sims/table.monthly_cost'),
                'visible' => false,
                'class' => 'text-right',
                'footerFormatter' => 'sumFormatter',
            ], [
                'field' => 'status',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.status'),
                'visible' => true,
                'formatter' => 'statuslabelsLinkObjFormatter',
            ], [
                'field' => 'assigned_to',
                'searchable' => false,
                'sortable' => false,
                'switchable' => true,
                'title' => trans('admin/hardware/form.checkedout_to'),
                'visible' => true,
                'formatter' => 'polymorphicItemFormatter',
            ], [
                'field' => 'company',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.company'),
                'visible' => false,
                'formatter' => 'companiesLinkObjFormatter',
            ], [
                'field' => 'location',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.location'),
                'visible' => true,
                'formatter' => 'locationsLinkObjFormatter',
            ], [
                'field' => 'department',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.department'),
                'visible' => true,
                'formatter' => 'departmentsLinkObjFormatter',
            ], [
                'field' => 'activation_date',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/table.activation_date'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ], [
                'field' => 'expiration_date',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/sims/table.expiration_date'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ], [
                'field' => 'last_checkout',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/hardware/table.checkout_date'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ], [
                'field' => 'last_checkin',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/hardware/table.last_checkin_date'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ], [
                'field' => 'expected_checkin',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/hardware/form.expected_checkin'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ], [
                'field' => 'notes',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.notes'),
                'visible' => false,
                'formatter' => 'notesFormatter',
            ], [
                'field' => 'created_by',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.created_by'),
                'visible' => false,
                'formatter' => 'usersLinkObjFormatter',
            ], [
                'field' => 'created_at',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.created_at'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ], [
                'field' => 'updated_at',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.updated_at'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ],
        ];

        // Secrets only appear in the column chooser for callers who may
        // see them; the API already omits the values otherwise.
        if (Gate::allows('viewSecrets', Sim::class)) {
            $layout = array_merge($layout, [
                [
                    'field' => 'pin',
                    'searchable' => false,
                    'sortable' => false,
                    'switchable' => true,
                    'title' => trans('admin/sims/table.pin'),
                    'visible' => false,
                ], [
                    'field' => 'puk',
                    'searchable' => false,
                    'sortable' => false,
                    'switchable' => true,
                    'title' => trans('admin/sims/table.puk'),
                    'visible' => false,
                ], [
                    'field' => 'activation_code',
                    'searchable' => false,
                    'sortable' => false,
                    'switchable' => true,
                    'title' => trans('admin/sims/table.activation_code'),
                    'visible' => false,
                ],
            ]);
        }

        // Custom fields from the SIM fieldset, rendered by the same
        // customFieldsFormatter the asset listing uses.
        foreach (Sim::allCustomFields() as $field) {
            $layout[] = [
                'field' => $field->db_column,
                'searchable' => false,
                'sortable' => false,
                'switchable' => true,
                'title' => e($field->name),
                'formatter' => 'customFieldsFormatter',
                'escape' => true,
                'class' => ($field->field_encrypted == '1') ? 'css-padlock' : '',
                'visible' => ($field->show_in_listview == '1'),
            ];
        }

        $layout[] = [
            'field' => 'checkincheckout',
            'searchable' => false,
            'sortable' => false,
            'switchable' => false,
            'title' => trans('general.checkin').'/'.trans('general.checkout'),
            'visible' => true,
            'formatter' => 'simsInOutFormatter',
            'printIgnore' => true,
            'class' => 'hidden-print',
        ];

        $layout[] = [
            'field' => 'actions',
            'searchable' => false,
            'sortable' => false,
            'switchable' => false,
            'title' => trans('table.actions'),
            'formatter' => 'simsActionsFormatter',
            'printIgnore' => true,
            'class' => 'hidden-print',
        ];

        return json_encode($layout);
    }

    /**
     * Generate html link to this items name.
     *
     * @return string
     */
    public function nameUrl()
    {
        if (auth()->user()->can('view', $this->model)) {
            return '<a href="'.route('sims.show', $this->id).'">'.e($this->display_name).'</a>';
        }

        return e($this->display_name);
    }

    /**
     * Url to view this item.
     *
     * @return string
     */
    public function viewUrl()
    {
        return route('sims.show', $this->id);
    }

    /**
     * Link target for SIM events on the calendar.
     */
    public function calendarUrl()
    {
        return route('sims.show', $this->id);
    }

    /**
     * Font Awesome class used for SIM cards everywhere in the UI.
     */
    public function icon()
    {
        return 'fas fa-sim-card';
    }
}
