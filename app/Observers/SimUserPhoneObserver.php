<?php

namespace App\Observers;

use App\Models\User;
use App\Services\SimPhoneMatcher;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps users.phone_e164 / users.mobile_e164 in sync with the free-text
 * phone / mobile fields, so SIM numbers can be matched to users. Runs on
 * every save, including the ones done by LDAP sync from AD.
 */
class SimUserPhoneObserver
{
    /**
     * Older migrations save users before the SIM migration has added the
     * E.164 columns; skip normalization until they exist.
     */
    private static ?bool $columnsExist = null;

    public function saving(User $user): void
    {
        // Only a positive result is cached: migrations may add the columns
        // later in the same process (fresh installs, the test suite).
        if (! self::$columnsExist) {
            self::$columnsExist = Schema::hasColumn('users', 'mobile_e164');
        }

        if (! self::$columnsExist) {
            return;
        }

        if (! $user->exists || $user->isDirty(['phone', 'mobile', 'country'])) {
            $user->forceFill(SimPhoneMatcher::normalizedUserNumbers($user));
        }
    }
}
