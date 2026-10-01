<?php

namespace App\Policies;

/**
 * SIM plans are managed with the SIM card permissions: viewing SIMs lets you
 * view plans, creating/editing/deleting SIMs lets you do the same to plans.
 */
class SimPlanPolicy extends SnipePermissionsPolicy
{
    protected function columnName()
    {
        return 'sims';
    }
}
