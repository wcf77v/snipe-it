<?php

namespace App\Observers;

use App\Models\Actionlog;
use App\Models\CustomField;
use App\Models\Sim;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class SimObserver
{
    /**
     * Columns that change as a side effect of checkout/checkin and are
     * already covered by the checkout/checkin log entries.
     */
    private const IGNORED_ON_UPDATE = [
        'updated_at',
        'assigned_to',
        'assigned_type',
        'last_checkout',
        'last_checkin',
        'expected_checkin',
    ];

    /**
     * Log an "update" entry with the changed fields. Secrets and encrypted
     * custom fields are compared in clear text (ciphertext changes on every
     * save) and are only ever logged masked.
     */
    public function updated(Sim $sim): void
    {
        $original = $sim->getRawOriginal();
        $current = $sim->getAttributes();
        $changed = [];

        // A checkout/checkin moves the SIM's location along with the
        // assignment; that move is part of the checkout/checkin log entry.
        $assignmentChanged = ($original['assigned_to'] ?? null) != ($current['assigned_to'] ?? null);

        foreach ($current as $key => $value) {
            if (in_array($key, self::IGNORED_ON_UPDATE, true) || ! array_key_exists($key, $original)) {
                continue;
            }

            if ($assignmentChanged && ($key === 'location_id')) {
                continue;
            }

            if (in_array($key, Sim::SECRET_FIELDS, true)) {
                if ($this->decryptString($original[$key]) !== $this->decryptString($value)) {
                    $changed[$key] = ['old' => Sim::SECRET_MASK, 'new' => Sim::SECRET_MASK];
                }

                continue;
            }

            if ($key === 'custom_fields') {
                $changed += $this->customFieldChanges($original[$key], $value);

                continue;
            }

            if ($original[$key] != $value) {
                $changed[$key] = ['old' => $original[$key], 'new' => $value];
            }
        }

        // A pure checkout/checkin save is logged by its own event.
        if (count($changed) === 0) {
            return;
        }

        $logAction = new Actionlog;
        $logAction->item_type = Sim::class;
        $logAction->item_id = $sim->id;
        $logAction->company_id = $sim->company_id;
        $logAction->created_at = date('Y-m-d H:i:s');
        $logAction->action_date = date('Y-m-d H:i:s');
        $logAction->created_by = auth()->id();
        $logAction->log_meta = json_encode($changed);
        if ($sim->imported) {
            $logAction->setActionSource('importer');
        }
        $logAction->logaction('update');
    }

    public function created(Sim $sim): void
    {
        $sim->logCreate();
    }

    public function deleting(Sim $sim): void
    {
        $logAction = new Actionlog;
        $logAction->item_type = Sim::class;
        $logAction->item_id = $sim->id;
        $logAction->company_id = $sim->company_id;
        $logAction->created_at = date('Y-m-d H:i:s');
        $logAction->action_date = date('Y-m-d H:i:s');
        $logAction->created_by = auth()->id();
        $logAction->logaction('delete');
    }

    public function restored(Sim $sim): void
    {
        $logAction = new Actionlog;
        $logAction->item_type = Sim::class;
        $logAction->item_id = $sim->id;
        $logAction->company_id = $sim->company_id;
        $logAction->created_at = date('Y-m-d H:i:s');
        $logAction->action_date = date('Y-m-d H:i:s');
        $logAction->created_by = auth()->id();
        $logAction->logaction('restore');
    }

    /**
     * Diff the custom_fields JSON per field, keyed by db_column so the
     * history view can resolve the field name like it does for assets.
     */
    private function customFieldChanges(?string $originalJson, ?string $currentJson): array
    {
        $old = json_decode($originalJson ?? '', true) ?: [];
        $new = json_decode($currentJson ?? '', true) ?: [];
        $encrypted = CustomField::whereIn('db_column', array_unique(array_merge(array_keys($old), array_keys($new))))
            ->where('field_encrypted', '1')
            ->pluck('db_column')
            ->all();

        $changes = [];

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $column) {
            $oldValue = $old[$column] ?? null;
            $newValue = $new[$column] ?? null;

            if (in_array($column, $encrypted, true)) {
                if ($this->decrypt($oldValue) !== $this->decrypt($newValue)) {
                    $changes[$column] = ['old' => Sim::SECRET_MASK, 'new' => Sim::SECRET_MASK];
                }

                continue;
            }

            if ($oldValue != $newValue) {
                $changes[$column] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        return $changes;
    }

    /**
     * Decrypt a value written by the `encrypted` cast (encryptString).
     */
    private function decryptString(?string $value): ?string
    {
        if (($value === null) || ($value === '')) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            return $value;
        }
    }

    /**
     * Decrypt a custom field value written with Crypt::encrypt.
     */
    private function decrypt(?string $value): ?string
    {
        if (($value === null) || ($value === '')) {
            return null;
        }

        try {
            return (string) Crypt::decrypt($value);
        } catch (DecryptException $e) {
            return $value;
        }
    }
}
