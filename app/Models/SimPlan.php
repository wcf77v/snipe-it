<?php

namespace App\Models;

use App\Helpers\Helper;
use App\Models\Traits\CompanyableTrait;
use App\Models\Traits\Searchable;
use App\Presenters\Presentable;
use App\Presenters\SimPlanPresenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;
use Watson\Validating\ValidatingTrait;

/**
 * A SIM plan (tariff / SIM type) is to SIM cards what an asset model is to
 * assets: it belongs to a mobile operator and decides which custom fieldset
 * every SIM on the plan gets, e.g. "M2M 1 GB" vs "Corporate unlimited".
 */
class SimPlan extends SnipeModel
{
    use CompanyableTrait;
    use HasFactory;
    use Presentable;
    use SoftDeletes;

    protected $presenter = SimPlanPresenter::class;

    protected $table = 'sim_plans';

    protected $casts = [
        'monthly_cost' => 'decimal:2',
    ];

    protected $rules = [
        'name' => 'required|string|max:191',
        'supplier_id' => 'nullable|integer|exists:suppliers,id',
        'fieldset_id' => 'nullable|integer|exists:custom_fieldsets,id',
        'company_id' => 'nullable|integer|exists:companies,id|fmcs_company',
        'monthly_cost' => 'nullable|numeric|gte:0|max:99999999999999999.99',
        'notes' => 'nullable|string|max:65535',
    ];

    protected $injectUniqueIdentifier = true;

    use ValidatingTrait;

    protected $fillable = [
        'name',
        'supplier_id',
        'fieldset_id',
        'company_id',
        'monthly_cost',
        'notes',
    ];

    use Searchable;

    protected $searchableAttributes = ['name', 'notes'];

    protected $searchableRelations = [
        'supplier' => ['name'],
        'fieldset' => ['name'],
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function fieldset(): BelongsTo
    {
        return $this->belongsTo(CustomFieldset::class, 'fieldset_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function sims(): HasMany
    {
        return $this->hasMany(Sim::class, 'sim_plan_id');
    }

    public function isDeletable(): bool
    {
        return Gate::allows('delete', $this)
            && ($this->sims_count ?? $this->sims()->count()) === 0
            && ($this->deleted_at == '');
    }

    public function setMonthlyCostAttribute($value): void
    {
        if (($value === null) || ($value === '')) {
            $this->attributes['monthly_cost'] = null;

            return;
        }

        $this->attributes['monthly_cost'] = is_numeric($value) ? $value : Helper::ParseCurrency($value);
    }
}
