<?php

namespace App\Policies;

use App\Models\User;

class SimPolicy extends CheckoutablePermissionsPolicy
{
    protected function columnName()
    {
        return 'sims';
    }

    /**
     * Determine whether the user can see PIN/PUK, the eSIM activation code
     * and encrypted SIM custom fields. Admins pass via before(); everyone
     * else needs the explicit sims.secrets permission.
     *
     * Unlike license keys, create/edit do NOT imply this: editors can
     * overwrite a secret without being able to read the current one.
     */
    public function viewSecrets(User $user, $item = null)
    {
        return $user->hasAccess('sims.secrets');
    }

    /**
     * Managing the SIM module settings (which custom fieldset applies to
     * SIMs) is an admin-level action.
     */
    public function manageSettings(User $user)
    {
        return $user->hasAccess('admin');
    }
}
