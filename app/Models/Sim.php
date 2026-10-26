<?php

namespace App\Models;

use App\Events\CheckoutableCheckedIn;
use App\Events\CheckoutableCheckedOut;
use App\Exceptions\CheckoutNotAllowed;
use App\Helpers\Helper;
use App\Http\Traits\UniqueUndeletedTrait;
use App\Models\Traits\CompanyableTrait;
use App\Models\Traits\HasCalendarEvents;
use App\Models\Traits\HasUploads;
use App\Models\Traits\Loggable;
use App\Models\Traits\Searchable;
use App\Presenters\Presentable;
use App\Presenters\SimPresenter;
use App\Services\PhoneNumberService;
use App\Services\SimPhoneMatcher;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Watson\Validating\ValidatingTrait;

/**
 * Model for SIM cards.
 *
 * A SIM card is a single checkoutable item identified by its ICCID. It can be
 * checked out to a user, an asset (phone, modem, router...) or a location.
 *
 * PIN, PUK and the eSIM activation code are encrypted at rest and are only
 * exposed to users who pass the `viewSecrets` policy check.
 *
 * Custom field values live in the `custom_fields` JSON column, keyed by the
 * CustomField db_column. The fieldset that applies to SIMs is configured in
 * settings.sim_fieldset_id.
 */
class Sim extends SnipeModel
{
    use CompanyableTrait;
    use HasCalendarEvents;
    use HasFactory;
    use HasUploads;
    use Loggable;
    use Presentable;
    use SoftDeletes;
    use UniqueUndeletedTrait;

    public const TYPE_PHYSICAL = 'physical';

    public const TYPE_ESIM = 'esim';

    public const TYPES = [self::TYPE_PHYSICAL, self::TYPE_ESIM];

    /**
     * Fields that are encrypted at rest and must never be logged or
     * serialized in clear text.
     */
    public const SECRET_FIELDS = ['pin', 'puk', 'activation_code'];

    /**
     * What a secret looks like to someone who isn't allowed to see it.
     */
    public const SECRET_MASK = '************';

    /**
     * Form/API fields an admin can make required in the SIM settings.
     * Enforced by SaveSimRequest only (not by the model), so tightening the
     * settings never blocks checkout/checkin of existing SIMs.
     */
    public const REQUIRABLE_FIELDS = [
        'msisdn',
        'iccid',
        'sim_plan_id',
        'assigned_user',
        'department_id',
        'account_number',
        'imsi',
        'activation_date',
        'expiration_date',
    ];

    /** Operator (supplier) is taken from the plan, so the plan is required by default. */
    public const DEFAULT_REQUIRED_FIELDS = ['msisdn', 'sim_plan_id'];

    protected $presenter = SimPresenter::class;

    protected $table = 'sims';

    protected $casts = [
        'pin' => 'encrypted',
        'puk' => 'encrypted',
        'activation_code' => 'encrypted',
        'custom_fields' => 'array',
        'last_checkout' => 'datetime',
        'last_checkin' => 'datetime',
        'expected_checkin' => 'date',
        'activation_date' => 'date',
        'expiration_date' => 'date',
    ];

    /**
     * Secrets and raw custom field storage are never part of the default
     * array/JSON serialization. API output goes through SimsTransformer,
     * which applies the permission checks.
     */
    protected $hidden = [
        'pin',
        'puk',
        'activation_code',
        'custom_fields',
    ];

    protected $rules = [
        'name' => 'nullable|string|max:191',
        'iccid' => 'nullable|string|regex:/^[0-9]{18,22}F?$/|unique_undeleted:sims,iccid',
        // Stored in E.164; SaveSimRequest validates the input per country.
        'msisdn' => 'nullable|string|max:32|regex:/^\+[1-9][0-9]{4,14}$/',
        'phone_country' => 'nullable|string|size:2',
        'sim_plan_id' => 'nullable|integer|exists:sim_plans,id',
        'imsi' => 'nullable|string|regex:/^[0-9]{6,15}$/',
        'sim_type' => 'required|in:physical,esim',
        'supplier_id' => 'nullable|integer|exists:suppliers,id',
        'account_number' => 'nullable|string|max:191',
        'status_id' => 'nullable|integer|exists:status_labels,id',
        'company_id' => 'nullable|integer|exists:companies,id|fmcs_company',
        'location_id' => 'nullable|exists:locations,id|fmcs_location',
        'department_id' => 'nullable|integer|exists:departments,id',
        'expected_checkin' => 'nullable|date',
        'activation_date' => 'nullable|date',
        'expiration_date' => 'nullable|date',
        'notes' => 'nullable|string|max:65535',
    ];

    /**
     * Whether the model should inject its identifier to the unique
     * validation rules before attempting validation.
     *
     * @var bool
     */
    protected $injectUniqueIdentifier = true;

    use ValidatingTrait;

    /**
     * The attributes that are mass assignable. Assignment fields and
     * created_by are deliberately absent: they only change through
     * checkOut()/checkIn() and the controllers.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'iccid',
        'msisdn',
        'imsi',
        'sim_type',
        'supplier_id',
        'sim_plan_id',
        'phone_country',
        'account_number',
        'pin',
        'puk',
        'activation_code',
        'status_id',
        'company_id',
        'location_id',
        'department_id',
        'expected_checkin',
        'activation_date',
        'expiration_date',
        'notes',
    ];

    use Searchable;

    /**
     * The attributes that should be included when searching the model.
     * Secrets are encrypted and are never searchable.
     *
     * @var array
     */
    protected $searchableAttributes = [
        'name',
        'iccid',
        'msisdn',
        'imsi',
        'account_number',
        'notes',
        'sim_type',
    ];

    /**
     * The relations and their attributes that should be included when searching the model.
     *
     * @var array
     */
    protected $searchableRelations = [
        'company' => ['name'],
        'location' => ['name'],
        'supplier' => ['name'],
        'simPlan' => ['name'],
        'status' => ['name'],
        'department' => ['name'],
        'adminuser' => ['first_name', 'last_name', 'display_name'],
    ];

    /**
     * Per-request cache of fieldsets keyed by plan id (0 = no plan / default).
     *
     * @var array<int, CustomFieldset|null>
     */
    private static array $fieldsetCache = [];

    /**
     * -----------------------------------------------
     * RELATIONSHIPS
     * -----------------------------------------------
     **/
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * The mobile operator is modeled as a supplier.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Statuslabel::class, 'status_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * The plan (tariff / SIM type) — decides the custom fieldset.
     */
    public function simPlan(): BelongsTo
    {
        return $this->belongsTo(SimPlan::class, 'sim_plan_id')->withTrashed();
    }

    /**
     * The user, asset or location this SIM is checked out to.
     */
    public function assignedTo(): MorphTo
    {
        return $this->morphTo('assigned', 'assigned_type', 'assigned_to')->withTrashed();
    }

    public function assetlog(): HasMany
    {
        return $this->hasMany(Actionlog::class, 'item_id')
            ->where('item_type', self::class)
            ->orderBy('created_at', 'desc')
            ->withTrashed();
    }

    /**
     * -----------------------------------------------
     * CHECKOUT / CHECKIN
     * -----------------------------------------------
     **/
    public function isDeletable(): bool
    {
        return Gate::allows('delete', $this)
            && ! $this->assigned_to
            && ($this->deleted_at == '');
    }

    /**
     * A SIM can be checked out when it isn't assigned, isn't deleted and its
     * status (if any) is deployable and not archived.
     */
    public function availableForCheckout(): bool
    {
        if ($this->assigned_to || $this->deleted_at) {
            return false;
        }

        if (! $this->status_id) {
            return true;
        }

        return $this->status
            && ($this->status->archived == '0')
            && ($this->status->deployable == '1');
    }

    public function availableForCheckIn(): bool
    {
        return (bool) $this->assigned_to;
    }

    public function assignedType(): ?string
    {
        return $this->assigned_type ? strtolower(class_basename($this->assigned_type)) : null;
    }

    public function checkedOutToUser(): bool
    {
        return $this->assigned_type === User::class;
    }

    public function checkedOutToAsset(): bool
    {
        return $this->assigned_type === Asset::class;
    }

    public function checkedOutToLocation(): bool
    {
        return $this->assigned_type === Location::class;
    }

    /**
     * Check the SIM out to a user, asset or location.
     *
     * @throws CheckoutNotAllowed
     */
    public function checkOut(User|Asset|Location $target, ?User $admin = null, $checkout_at = null, $expected_checkin = null, ?string $note = null): bool
    {
        if (! $this->availableForCheckout()) {
            throw new CheckoutNotAllowed(trans('admin/sims/message.checkout.not_available'));
        }

        if (! $this->canCheckoutTo($target)) {
            throw new CheckoutNotAllowed(trans('admin/sims/message.checkout.company_mismatch'));
        }

        $originalValues = $this->getRawOriginal();

        $this->assignedTo()->associate($target);
        $this->last_checkout = $checkout_at ?: now();
        $this->expected_checkin = $expected_checkin ?: null;

        if ($target instanceof Location) {
            $this->location_id = $target->id;
        } elseif ($target->location_id) {
            $this->location_id = $target->location_id;
        }

        if (! $this->save()) {
            return false;
        }

        event(new CheckoutableCheckedOut($this, $target, $admin ?? auth()->user(), $note, $originalValues));

        if ($target instanceof User) {
            SimPhoneMatcher::writeBackToUser($this, $target);
        }

        return true;
    }

    /**
     * Check the SIM back in from whatever it is assigned to.
     */
    public function checkIn(?string $note = null, $checkin_at = null, ?int $location_id = null): bool
    {
        $target = $this->assignedTo;

        if (! $target) {
            // Orphaned assignment (target was hard-deleted): just clear it.
            $this->assigned_to = null;
            $this->assigned_type = null;
            $this->expected_checkin = null;
            $this->last_checkin = $checkin_at ?: now();
            $saved = $this->save();
            if ($saved) {
                $this->logForceCheckin($note);
            }

            return $saved;
        }

        $originalValues = $this->getRawOriginal();

        $this->assigned_to = null;
        $this->assigned_type = null;
        $this->expected_checkin = null;
        $this->last_checkin = $checkin_at ?: now();

        if ($location_id) {
            $this->location_id = $location_id;
        }

        if (! $this->save()) {
            return false;
        }

        $actionDate = $this->last_checkin instanceof \DateTimeInterface
            ? $this->last_checkin->format('Y-m-d H:i:s')
            : (string) $this->last_checkin;

        event(new CheckoutableCheckedIn($this, $target, auth()->user(), $note, $actionDate, $originalValues));

        return true;
    }

    /**
     * SIMs use the shared EULA (if any) and never require acceptance; the
     * CheckoutableListener skips SIM notifications entirely.
     */
    public function requireAcceptance(): bool
    {
        return false;
    }

    /**
     * Whether the user gets an email when a SIM is checked out to / in from
     * them. Assets read this from their category; SIMs have a module setting.
     */
    public function checkin_email(): bool
    {
        return (bool) Setting::getSettings()?->sim_checkout_email;
    }

    /**
     * -----------------------------------------------
     * STATUS (same semantics as assets)
     * -----------------------------------------------
     **/

    /**
     * The status label new SIMs get: the default label, otherwise the first
     * deployable, non-archived one.
     */
    public static function defaultStatusId(): ?int
    {
        $id = Statuslabel::where('default_label', 1)->value('id')
            ?? Statuslabel::where('deployable', 1)->where('archived', 0)->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * "deployed" when checked out, otherwise the label's type
     * (deployable = Ready to Deploy, pending, undeployable, archived) —
     * the same meta status assets show.
     */
    public function statusMeta(): string
    {
        if ($this->assigned_to) {
            return 'deployed';
        }

        return $this->status ? $this->status->getStatuslabelType() : 'deployable';
    }

    /**
     * Filter by the asset-style status types used in the sidebar/API:
     * Deployed, RTD, Pending, Undeployable, Archived.
     */
    public function scopeStatusType(Builder $query, string $type): Builder
    {
        $statusIds = fn (array $where) => Statuslabel::where($where)->pluck('id');

        return match (strtolower($type)) {
            'deployed' => $query->whereNotNull('sims.assigned_to'),
            'rtd' => $query->whereNull('sims.assigned_to')->where(function (Builder $q) use ($statusIds) {
                $q->whereNull('sims.status_id')
                    ->orWhereIn('sims.status_id', $statusIds(['deployable' => 1, 'archived' => 0, 'pending' => 0]));
            }),
            'pending' => $query->whereIn('sims.status_id', $statusIds(['pending' => 1])),
            'undeployable' => $query->whereIn('sims.status_id', $statusIds(['deployable' => 0, 'pending' => 0, 'archived' => 0])),
            'archived' => $query->whereIn('sims.status_id', $statusIds(['archived' => 1])),
            default => $query,
        };
    }

    /**
     * -----------------------------------------------
     * CALENDAR
     * -----------------------------------------------
     **/

    /**
     * Dates that show up on the Snipe-IT calendar.
     */
    public function calendarEventDefinitions(): array
    {
        return [
            [
                'field' => 'expiration_date',
                'event_type' => 'sim.expiration',
            ],
            [
                'field' => 'expected_checkin',
                'event_type' => 'sim.expected_checkin',
            ],
        ];
    }

    /**
     * The fields configured as required in the SIM settings.
     *
     * @return string[]
     */
    public static function requiredFields(): array
    {
        $configured = Setting::getSettings()?->sim_required_fields;
        $fields = ($configured === null) ? self::DEFAULT_REQUIRED_FIELDS : array_map('trim', explode(',', $configured));

        return array_values(array_intersect(self::REQUIRABLE_FIELDS, $fields));
    }

    public static function isFieldRequired(string $field): bool
    {
        return in_array($field, self::requiredFields(), true);
    }

    /**
     * -----------------------------------------------
     * CUSTOM FIELDS
     * -----------------------------------------------
     **/

    /**
     * The fieldset for SIMs on the given plan: the plan's own fieldset if it
     * has one, otherwise the default SIM fieldset from the settings.
     * Mirrors how assets get their fieldset from their model.
     */
    public static function fieldsetForPlan(?int $planId): ?CustomFieldset
    {
        $key = (int) $planId;

        if (! array_key_exists($key, self::$fieldsetCache)) {
            $fieldsetId = null;

            if ($planId) {
                $fieldsetId = SimPlan::withTrashed()->whereKey($planId)->value('fieldset_id');
            }

            $fieldsetId ??= Setting::getSettings()?->sim_fieldset_id;

            self::$fieldsetCache[$key] = $fieldsetId ? CustomFieldset::with('fields')->find($fieldsetId) : null;
        }

        return self::$fieldsetCache[$key];
    }

    /**
     * Forget cached fieldsets (after SIM settings or a plan change, and in tests).
     */
    public static function flushCustomFieldset(): void
    {
        self::$fieldsetCache = [];
    }

    /**
     * The fieldset that applies to this SIM.
     */
    public function customFieldset(): ?CustomFieldset
    {
        return self::fieldsetForPlan($this->sim_plan_id);
    }

    /**
     * The custom fields that apply to this SIM.
     *
     * @return \Illuminate\Support\Collection<int, CustomField>
     */
    public function customFields()
    {
        return $this->customFieldset()?->fields ?? collect();
    }

    /**
     * Every custom field used by any SIM (default fieldset plus all plan
     * fieldsets), for listing columns.
     *
     * @return \Illuminate\Support\Collection<int, CustomField>
     */
    public static function allCustomFields()
    {
        $fieldsetIds = SimPlan::whereNotNull('fieldset_id')->pluck('fieldset_id');

        if ($defaultId = Setting::getSettings()?->sim_fieldset_id) {
            $fieldsetIds->push($defaultId);
        }

        if ($fieldsetIds->isEmpty()) {
            return collect();
        }

        return CustomField::whereHas('fieldset', fn ($query) => $query->whereIn('custom_fieldsets.id', $fieldsetIds->unique()))
            ->orderBy('name')
            ->get();
    }

    /**
     * The stored (possibly encrypted) value of a custom field.
     */
    public function rawCustomFieldValue(CustomField $field): ?string
    {
        $values = $this->custom_fields ?? [];

        return isset($values[$field->db_column]) ? (string) $values[$field->db_column] : null;
    }

    /**
     * The clear-text value of a custom field. Callers are responsible for
     * checking the viewSecrets policy before showing encrypted values.
     */
    public function customFieldValue(CustomField $field): ?string
    {
        $value = $this->rawCustomFieldValue($field);

        if (($value === null) || ($value === '') || ($field->field_encrypted != '1')) {
            return $value;
        }

        try {
            return Crypt::decrypt($value);
        } catch (DecryptException $e) {
            return null;
        }
    }

    /**
     * Store a custom field value, encrypting it when the field requires it.
     * Arrays (checkbox fields) are flattened the same way assets do it.
     */
    public function setCustomFieldValue(CustomField $field, $value): void
    {
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        $values = $this->custom_fields ?? [];

        if (($value === null) || ($value === '')) {
            unset($values[$field->db_column]);
        } else {
            $values[$field->db_column] = ($field->field_encrypted == '1') ? Crypt::encrypt($value) : (string) $value;
        }

        $this->custom_fields = $values;
    }

    /**
     * Validation rules for the custom field inputs of a SIM form/API request.
     * Values are validated in clear text, before they are encrypted.
     *
     * @param  int|null  $planId  The plan the SIM will be on after saving.
     * @param  bool  $partial  When true (API PATCH), fields are only validated if present.
     */
    public static function customFieldRequestRules(?int $planId, bool $partial = false): array
    {
        $rules = [];
        $canEditEncrypted = Gate::allows('viewSecrets', self::class);

        foreach (self::fieldsetForPlan($planId)?->fields ?? collect() as $field) {
            $rule = [];

            if ($partial) {
                $rule[] = 'sometimes';
            }

            // Encrypted fields aren't rendered for users who can't see them,
            // so they can't be required from those users either.
            $required = ($field->pivot->required == '1')
                && (($field->field_encrypted != '1') || $canEditEncrypted);

            $rule[] = $required ? 'required' : 'nullable';

            if (($field->getRawOriginal('format') != '') && ($field->element != 'checkbox')) {
                $rule[] = $field->getRawOriginal('format');
            }

            if ($field->element == 'checkbox') {
                $rule[] = 'checkboxes';
            } else {
                $rule[] = 'not_array';
            }

            if ($field->element == 'radio') {
                $rule[] = 'radio_buttons';
            }

            $rules[$field->db_column] = $rule;
        }

        return $rules;
    }

    /**
     * -----------------------------------------------
     * ACCESSORS
     * -----------------------------------------------
     **/

    /**
     * Name to show in lists, breadcrumbs and logs: the friendly name when
     * set, otherwise the phone number, otherwise the ICCID.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->name) {
                    return $this->name;
                }

                return $this->formatted_msisdn ?: ($this->iccid ?: '#'.$this->id);
            },
        );
    }

    /**
     * The phone number in international notation, e.g. "+7 900 123-45-67".
     *
     * @return Attribute<string|null, never>
     */
    protected function formattedMsisdn(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->msisdn ? PhoneNumberService::format($this->msisdn) : null,
        );
    }


    /**
     * Strip spaces/dashes from ICCID so "8970 1012 3456 ..." is stored the
     * way it's printed on the card but without separators.
     */
    public function setIccidAttribute($value): void
    {
        $this->attributes['iccid'] = $value !== null ? strtoupper(preg_replace('/[\s\-]/', '', (string) $value)) : null;
    }

    public function setImsiAttribute($value): void
    {
        $this->attributes['imsi'] = ($value !== null && $value !== '') ? preg_replace('/\s/', '', (string) $value) : null;
    }

    public function setMsisdnAttribute($value): void
    {
        $this->attributes['msisdn'] = ($value !== null && $value !== '') ? trim((string) $value) : null;
    }

    public function setSimTypeAttribute($value): void
    {
        $this->attributes['sim_type'] = in_array($value, self::TYPES, true) ? $value : self::TYPE_PHYSICAL;
    }

    /**
     * The monthly fee comes from the SIM's plan; SIMs don't carry their own.
     *
     * @return Attribute<string|null, never>
     */
    protected function monthlyCost(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->simPlan?->monthly_cost,
        );
    }

    public function setExpectedCheckinAttribute($value): void
    {
        $this->attributes['expected_checkin'] = ($value === null || $value === '') ? null : date('Y-m-d', strtotime((string) $value));
    }

    /**
     * -----------------------------------------------
     * QUERY SCOPES
     * -----------------------------------------------
     **/
    public function scopeOrderCompany(Builder $query, string $order): Builder
    {
        return $query->leftJoin('companies as sim_company_sort', 'sims.company_id', '=', 'sim_company_sort.id')
            ->select('sims.*')
            ->orderBy('sim_company_sort.name', $order);
    }

    public function scopeOrderLocation(Builder $query, string $order): Builder
    {
        return $query->leftJoin('locations as sim_location_sort', 'sims.location_id', '=', 'sim_location_sort.id')
            ->select('sims.*')
            ->orderBy('sim_location_sort.name', $order);
    }

    public function scopeOrderSupplier(Builder $query, string $order): Builder
    {
        return $query->leftJoin('suppliers as sim_supplier_sort', 'sims.supplier_id', '=', 'sim_supplier_sort.id')
            ->select('sims.*')
            ->orderBy('sim_supplier_sort.name', $order);
    }

    public function scopeOrderSimPlan(Builder $query, string $order): Builder
    {
        return $query->leftJoin('sim_plans as sim_plan_sort', 'sims.sim_plan_id', '=', 'sim_plan_sort.id')
            ->select('sims.*')
            ->orderBy('sim_plan_sort.name', $order);
    }

    public function scopeOrderDepartment(Builder $query, string $order): Builder
    {
        return $query->leftJoin('departments as sim_department_sort', 'sims.department_id', '=', 'sim_department_sort.id')
            ->select('sims.*')
            ->orderBy('sim_department_sort.name', $order);
    }

    public function scopeOrderStatus(Builder $query, string $order): Builder
    {
        return $query->leftJoin('status_labels as sim_status_sort', 'sims.status_id', '=', 'sim_status_sort.id')
            ->select('sims.*')
            ->orderBy('sim_status_sort.name', $order);
    }

    public function scopeOrderByCreatedBy(Builder $query, string $order): Builder
    {
        return $query->leftJoin('users as sim_admin_sort', 'sims.created_by', '=', 'sim_admin_sort.id')
            ->select('sims.*')
            ->orderBy('sim_admin_sort.first_name', $order)
            ->orderBy('sim_admin_sort.last_name', $order);
    }

    public function scopeCheckedOut(Builder $query): Builder
    {
        return $query->whereNotNull('sims.assigned_to');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('sims.assigned_to');
    }
}
