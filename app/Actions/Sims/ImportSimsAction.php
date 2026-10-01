<?php

namespace App\Actions\Sims;

use App\Exceptions\CheckoutNotAllowed;
use App\Models\Department;
use App\Models\Location;
use App\Models\Sim;
use App\Models\SimPlan;
use App\Models\Statuslabel;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PhoneNumberService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Imports SIM cards from a CSV file.
 *
 * - Header names are matched case-insensitively against the internal keys
 *   (msisdn, iccid, ...) and against the SIM table column titles in every
 *   installed language, so a file exported from the SIM list can be
 *   re-imported as is.
 * - A row updates the SIM with the same ICCID, otherwise the SIM with the
 *   same phone number, otherwise it creates a new SIM.
 * - Operator, plan, department, status and location are looked up by name
 *   (company-scoped). Operators and plans can optionally be created.
 * - The user column checks the SIM out through the normal checkout flow.
 * - $dryRun runs everything inside a transaction that is rolled back.
 */
class ImportSimsAction
{
    /**
     * Internal column keys and the translation keys of their titles.
     */
    private const COLUMNS = [
        'msisdn' => 'admin/sims/table.msisdn',
        'phone_country' => 'admin/sims/table.phone_country',
        'iccid' => 'admin/sims/table.iccid',
        'operator' => 'admin/sims/table.operator',
        'plan' => 'admin/sims/table.plan',
        'user' => 'admin/hardware/form.checkedout_to',
        'department' => 'general.department',
        'name' => 'admin/sims/table.name',
        'sim_type' => 'admin/sims/table.sim_type',
        'status' => 'general.status',
        'location' => 'general.location',
        'account_number' => 'admin/sims/table.account_number',
        'imsi' => 'admin/sims/table.imsi',
        'activation_date' => 'admin/sims/table.activation_date',
        'expiration_date' => 'admin/sims/table.expiration_date',
        'notes' => 'general.notes',
    ];

    public const MAX_ROWS = 5000;

    /**
     * Column keys in template order.
     *
     * @return string[]
     */
    public static function columns(): array
    {
        return array_keys(self::COLUMNS);
    }

    /**
     * @return array{rows: list<array{line: int, status: string, sim: ?string, messages: list<string>}>, created: int, updated: int, failed: int, dry_run: bool}
     */
    public static function run(UploadedFile $file, bool $dryRun, bool $createMissing = false): array
    {
        $result = ['rows' => [], 'created' => 0, 'updated' => 0, 'failed' => 0, 'dry_run' => $dryRun];

        [$header, $records] = self::readCsv($file->getRealPath());
        $map = self::mapHeader($header);

        if (! in_array('msisdn', $map, true) && ! in_array('iccid', $map, true)) {
            $result['rows'][] = ['line' => 1, 'status' => 'error', 'sim' => null, 'messages' => [trans('admin/sims/import.missing_key_columns')]];
            $result['failed']++;

            return $result;
        }

        DB::beginTransaction();

        try {
            foreach ($records as $index => $values) {
                $line = $index + 2;
                $row = [];
                foreach ($map as $position => $key) {
                    $row[$key] = isset($values[$position]) ? trim((string) $values[$position]) : '';
                }

                if (count(array_filter($row, fn ($value) => $value !== '')) === 0) {
                    continue;
                }

                $outcome = self::importRow($row, $createMissing);
                $outcome['line'] = $line;
                $result['rows'][] = $outcome;
                $result[$outcome['status'] === 'error' ? 'failed' : $outcome['status']]++;
            }
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $result;
    }

    /**
     * @return array{status: string, sim: ?string, messages: list<string>}
     */
    private static function importRow(array $row, bool $createMissing): array
    {
        $errors = [];

        $country = PhoneNumberService::cleanCountry($row['phone_country'] ?? null) ?? PhoneNumberService::defaultCountry();
        $msisdn = null;
        if (($row['msisdn'] ?? '') !== '') {
            $msisdn = PhoneNumberService::toE164($row['msisdn'], $country);
            if (! $msisdn) {
                $errors[] = trans('admin/sims/message.invalid_phone', ['example' => PhoneNumberService::example($country)]);
            }
        }

        $iccid = (($row['iccid'] ?? '') !== '') ? strtoupper(preg_replace('/[\s\-]/', '', $row['iccid'])) : null;

        // Find the SIM to update: ICCID first, then phone number.
        $sim = null;
        if ($iccid) {
            $sim = Sim::where('iccid', $iccid)->first();
        }
        if (! $sim && $msisdn) {
            $sim = Sim::where('msisdn', $msisdn)->first();
        }
        $isNew = ! $sim;
        $sim ??= new Sim;

        if (Gate::denies($isNew ? 'create' : 'update', $isNew ? Sim::class : $sim)) {
            return ['status' => 'error', 'sim' => $iccid ?? $msisdn, 'messages' => [trans('general.insufficient_permissions')]];
        }

        $sim->iccid = $iccid ?? $sim->iccid;
        if ($msisdn) {
            $sim->msisdn = $msisdn;
            $sim->phone_country = PhoneNumberService::regionOf($msisdn) ?? $country;
        }

        foreach (['name', 'account_number', 'imsi', 'notes'] as $plain) {
            if (($row[$plain] ?? '') !== '') {
                $sim->{$plain} = $row[$plain];
            }
        }

        if (($row['sim_type'] ?? '') !== '') {
            $type = mb_strtolower($row['sim_type']);
            $sim->sim_type = (str_contains($type, 'esim') || ($type === trim(mb_strtolower(trans('admin/sims/general.type_esim'))))) ? Sim::TYPE_ESIM : Sim::TYPE_PHYSICAL;
        }

        foreach (['activation_date', 'expiration_date'] as $dateField) {
            if (($row[$dateField] ?? '') !== '') {
                $timestamp = strtotime($row[$dateField]);
                $timestamp ? ($sim->{$dateField} = date('Y-m-d', $timestamp)) : ($errors[] = trans('validation.date', ['attribute' => trans('admin/sims/table.'.$dateField)]));
            }
        }

        // Lookups by name (Companyable models are scoped to the importer's companies).
        if (($row['operator'] ?? '') !== '') {
            $supplier = Supplier::where('name', $row['operator'])->first();
            if (! $supplier && $createMissing && Gate::allows('create', Supplier::class)) {
                $supplier = Supplier::create(['name' => $row['operator']]);
            }
            $supplier ? ($sim->supplier_id = $supplier->id) : ($errors[] = trans('admin/sims/import.not_found', ['what' => trans('admin/sims/table.operator'), 'value' => $row['operator']]));
        }

        if (($row['plan'] ?? '') !== '') {
            $plan = SimPlan::where('name', $row['plan'])->first();
            if (! $plan && $createMissing && Gate::allows('create', SimPlan::class)) {
                $plan = new SimPlan(['name' => $row['plan'], 'supplier_id' => $sim->supplier_id]);
                $plan->created_by = auth()->id();
                $plan->save();
            }
            $plan ? ($sim->sim_plan_id = $plan->id) : ($errors[] = trans('admin/sims/import.not_found', ['what' => trans('admin/sims/table.plan'), 'value' => $row['plan']]));
        }

        if (! $sim->supplier_id && $sim->sim_plan_id) {
            $sim->supplier_id = SimPlan::whereKey($sim->sim_plan_id)->value('supplier_id');
        }

        foreach (['department' => Department::class, 'status' => Statuslabel::class, 'location' => Location::class] as $key => $class) {
            if (($row[$key] ?? '') === '') {
                continue;
            }
            $found = $class::where('name', $row[$key])->first();
            $column = ['department' => 'department_id', 'status' => 'status_id', 'location' => 'location_id'][$key];
            $found ? ($sim->{$column} = $found->id) : ($errors[] = trans('admin/sims/import.not_found', ['what' => trans(self::COLUMNS[$key]), 'value' => $row[$key]]));
        }

        $owner = null;
        if (($row['user'] ?? '') !== '') {
            $owner = self::findUser($row['user']);
            if (! $owner) {
                $errors[] = trans('admin/sims/import.not_found', ['what' => trans('general.user'), 'value' => $row['user']]);
            }
        }

        if ($owner && ! $sim->department_id) {
            $sim->department_id = $owner->department_id;
        }

        // Required fields from the SIM settings (assigned_user = user column).
        foreach (Sim::requiredFields() as $field) {
            $present = ($field === 'assigned_user') ? ($owner || $sim->assigned_to) : ! empty($sim->{$field});
            if (! $present) {
                $errors[] = trans('validation.required', ['attribute' => trans('admin/sims/general.field_'.$field)]);
            }
        }

        if ($errors) {
            return ['status' => 'error', 'sim' => $iccid ?? $msisdn, 'messages' => $errors];
        }

        if (! $sim->exists) {
            $sim->created_by = auth()->id();
            $sim->status_id ??= Sim::defaultStatusId();
        }

        // Each row is atomic: a failed checkout undoes that row's changes only.
        try {
            DB::transaction(function () use ($sim, $owner) {
                if (! $sim->save()) {
                    throw new \RuntimeException(implode(' ', $sim->getErrors()->all()));
                }

                if ($owner && ! ($sim->checkedOutToUser() && ((int) $sim->assigned_to === (int) $owner->id))) {
                    if (Gate::denies('checkout', $sim)) {
                        throw new \RuntimeException(trans('admin/sims/message.assign_not_allowed'));
                    }
                    if ($sim->assigned_to && (Gate::denies('checkin', $sim) || ! $sim->checkIn(trans('admin/sims/import.note')))) {
                        throw new \RuntimeException(trans('admin/sims/message.checkin.error'));
                    }
                    $sim->refresh();
                    if (! $sim->checkOut($owner, auth()->user(), null, null, trans('admin/sims/import.note'))) {
                        throw new \RuntimeException(trans('admin/sims/message.checkout.error'));
                    }
                }
            });
        } catch (CheckoutNotAllowed|\RuntimeException $e) {
            return ['status' => 'error', 'sim' => $iccid ?? $msisdn, 'messages' => [$e->getMessage()]];
        }

        return ['status' => $isNew ? 'created' : 'updated', 'sim' => $sim->display_name, 'messages' => []];
    }

    /**
     * User by username, email or employee number. Also understands the
     * "Display Name (username)" format of the SIM list export.
     */
    private static function findUser(string $value): ?User
    {
        if (preg_match('/\(([^()]+)\)\s*$/', $value, $matches)) {
            $value = trim($matches[1]);
        }

        return User::where('username', $value)
            ->orWhere('email', $value)
            ->orWhere('employee_num', $value)
            ->first();
    }

    /**
     * Map CSV header positions to internal column keys.
     *
     * @return array<int, string>
     */
    private static function mapHeader(array $header): array
    {
        $aliases = [];
        $locales = array_unique(array_merge([app()->getLocale(), 'en-US'], array_map('basename', glob(lang_path('*'), GLOB_ONLYDIR) ?: [])));

        foreach (self::COLUMNS as $key => $translationKey) {
            $aliases[self::normalizeHeader($key)] = $key;
            foreach ($locales as $locale) {
                $aliases[self::normalizeHeader(trans($translationKey, [], $locale))] = $key;
            }
        }

        // A few common synonyms.
        foreach (['phone' => 'msisdn', 'number' => 'msisdn', 'телефон' => 'msisdn', 'номер' => 'msisdn',
            'supplier' => 'operator', 'оператор' => 'operator', 'тариф' => 'plan', 'sim_plan' => 'plan',
            'assigned_to' => 'user', 'username' => 'user', 'пользователь' => 'user', 'department_id' => 'department',
            'country' => 'phone_country', 'страна' => 'phone_country'] as $alias => $key) {
            $aliases[self::normalizeHeader($alias)] = $key;
        }

        $map = [];
        foreach ($header as $position => $title) {
            $normalized = self::normalizeHeader((string) $title);
            if (isset($aliases[$normalized]) && ! in_array($aliases[$normalized], $map, true)) {
                $map[$position] = $aliases[$normalized];
            }
        }

        return $map;
    }

    private static function normalizeHeader(string $title): string
    {
        $title = preg_replace('/^\xEF\xBB\xBF/', '', $title);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $title)));
    }

    /**
     * Read the CSV, detecting ";" vs "," as delimiter.
     *
     * @return array{0: array, 1: array<int, array>}
     */
    private static function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $firstLine = (string) fgets($handle);
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter, '"', '') ?: [];
        $records = [];

        while ((($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) && (count($records) < self::MAX_ROWS)) {
            $records[] = $values;
        }

        fclose($handle);

        return [$header, $records];
    }
}
