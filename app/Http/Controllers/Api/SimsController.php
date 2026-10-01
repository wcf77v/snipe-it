<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sims\SaveSimAction;
use App\Exceptions\CheckoutNotAllowed;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSimRequest;
use App\Http\Requests\SimCheckinRequest;
use App\Http\Requests\SimCheckoutRequest;
use App\Http\Traits\CheckInOutTrait;
use App\Http\Transformers\ActionlogsTransformer;
use App\Http\Transformers\SelectlistTransformer;
use App\Http\Transformers\SimsTransformer;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Location;
use App\Models\Sim;
use App\Models\User;
use App\Services\PhoneNumberService;
use App\Services\SimPhoneMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SimsController extends Controller
{
    use CheckInOutTrait;

    /**
     * Polymorphic assignment targets accepted by the assigned_type filter.
     * Allowlisted so the input never reaches a class lookup unchecked.
     */
    private const ASSIGNED_TYPES = [
        'user' => User::class,
        'asset' => Asset::class,
        'location' => Location::class,
        User::class => User::class,
        Asset::class => Asset::class,
        Location::class => Location::class,
    ];

    /**
     * List SIM cards.
     *
     * Filters: search, filter (advanced search JSON), company_id, location_id,
     * supplier_id, status_id, sim_type, iccid, msisdn, assigned_to +
     * assigned_type, status=checked_out|available, deleted=true
     */
    public function index(Request $request): JsonResponse|array
    {
        $this->authorize('index', Sim::class);

        // Plain table columns that can be sorted on directly. Relations are
        // handled in the switch below. Secrets are never sortable.
        $allowed_columns = [
            'id',
            'name',
            'iccid',
            'msisdn',
            'imsi',
            'sim_type',
            'account_number',
            'last_checkout',
            'last_checkin',
            'expected_checkin',
            'activation_date',
            'expiration_date',
            'notes',
            'created_at',
            'updated_at',
        ];

        $sims = Sim::select('sims.*')
            ->with('company', 'location', 'department', 'supplier', 'simPlan', 'status', 'adminuser', 'assignedTo');

        if ($request->input('deleted') === 'true') {
            $sims->onlyTrashed();
        }

        if ($request->filled('filter') || $request->filled('search')) {
            $sims->TextSearch($request->input('filter') ? $request->input('filter') : $request->input('search'));
        }

        foreach (['iccid', 'imsi', 'sim_type', 'account_number'] as $exactFilter) {
            if ($request->filled($exactFilter) && is_string($request->input($exactFilter))) {
                $sims->where('sims.'.$exactFilter, '=', $request->input($exactFilter));
            }
        }

        if ($request->filled('company_id')) {
            if ($request->boolean('expand_company_hierarchy')) {
                $sims->whereIn('sims.company_id', Company::reachableCompanyIds($request->input('company_id')));
            } else {
                $sims->where('sims.company_id', '=', (int) $request->input('company_id'));
            }
        }

        // Phone number filter accepts any notation and matches the stored E.164.
        if ($request->filled('msisdn') && is_string($request->input('msisdn'))) {
            $sims->where('sims.msisdn', '=', PhoneNumberService::toE164($request->input('msisdn'), $request->input('phone_country')) ?? $request->input('msisdn'));
        }

        foreach (['location_id', 'department_id', 'supplier_id', 'status_id', 'sim_plan_id'] as $idFilter) {
            if ($request->filled($idFilter)) {
                $sims->where('sims.'.$idFilter, '=', (int) $request->input($idFilter));
            }
        }

        if ($request->filled('assigned_to') && $request->filled('assigned_type')) {
            $assignedType = self::ASSIGNED_TYPES[$request->input('assigned_type')] ?? null;
            if ($assignedType) {
                $sims->where('sims.assigned_to', '=', (int) $request->input('assigned_to'))
                    ->where('sims.assigned_type', '=', $assignedType);
            } else {
                $sims->whereRaw('1 = 0');
            }
        }

        // Asset-style status types: Deployed, RTD, Pending, Undeployable, Archived.
        if ($request->filled('status_type') && is_string($request->input('status_type'))) {
            $sims->StatusType($request->input('status_type'));
        }

        switch ($request->input('status')) {
            case 'checked_out':
            case 'deployed':
                $sims->CheckedOut();
                break;
            case 'available':
                $sims->Available();
                break;
        }

        $total = $sims->count();
        $offset = ($request->input('offset') > $total) ? $total : app('api_offset_value');
        $limit = app('api_limit_value');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = $request->input('sort');

        switch ($sort) {
            case 'company':
                $sims->OrderCompany($order);
                break;
            case 'location':
                $sims->OrderLocation($order);
                break;
            case 'supplier':
                $sims->OrderSupplier($order);
                break;
            case 'department':
                $sims->OrderDepartment($order);
                break;
            case 'status':
                $sims->OrderStatus($order);
                break;
            case 'sim_plan':
                $sims->OrderSimPlan($order);
                break;
            case 'created_by':
                $sims->OrderByCreatedBy($order);
                break;
            default:
                $sims->orderBy('sims.'.(in_array($sort, $allowed_columns) ? $sort : 'created_at'), $order);
                break;
        }

        $sims = $sims->skip($offset)->take($limit)->get();

        return (new SimsTransformer)->transformSims($sims, $total);
    }

    public function show(Sim $sim): JsonResponse|array
    {
        $this->authorize('view', $sim);

        return (new SimsTransformer)->transformSim($sim);
    }

    /**
     * Look a SIM up by its ICCID (spaces and dashes are ignored).
     */
    public function showByIccid(string $iccid): JsonResponse|array
    {
        $this->authorize('index', Sim::class);

        $normalized = strtoupper(preg_replace('/[\s\-]/', '', $iccid));

        if (! $sim = Sim::where('iccid', $normalized)->first()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/sims/message.does_not_exist')), 200);
        }

        $this->authorize('view', $sim);

        return (new SimsTransformer)->transformSim($sim);
    }

    public function store(SaveSimRequest $request): JsonResponse
    {
        $this->authorize('create', Sim::class);

        $sim = new Sim;

        if (SaveSimAction::run($sim, $request)) {
            return response()->json(Helper::formatStandardApiResponse('success', (new SimsTransformer)->transformSim($sim->fresh()), trans('admin/sims/message.create.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $sim->getErrors()));
    }

    public function update(SaveSimRequest $request, Sim $sim): JsonResponse
    {
        $this->authorize('update', $sim);

        if (SaveSimAction::run($sim, $request)) {
            return response()->json(Helper::formatStandardApiResponse('success', (new SimsTransformer)->transformSim($sim->fresh()), trans('admin/sims/message.update.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $sim->getErrors()));
    }

    public function destroy(Sim $sim): JsonResponse
    {
        $this->authorize('delete', $sim);

        if ($sim->assigned_to) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/sims/message.delete.assigned')));
        }

        $sim->delete();

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/sims/message.delete.success')));
    }

    public function restore(int $simId): JsonResponse
    {
        $sim = Sim::withTrashed()->find($simId);

        if (! $sim) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/sims/message.does_not_exist')), 200);
        }

        $this->authorize('create', Sim::class);
        $this->authorize('view', $sim);

        if ($sim->deleted_at == '') {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('general.not_deleted', ['item_type' => trans('admin/sims/general.sim')])), 200);
        }

        if (Sim::where('iccid', $sim->iccid)->exists()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/sims/message.restore.duplicate')), 200);
        }

        $sim->restore();

        return response()->json(Helper::formatStandardApiResponse('success', (new SimsTransformer)->transformSim($sim), trans('admin/sims/message.restore.success')));
    }

    /**
     * Check a SIM out to a user, asset or location.
     *
     * Body: checkout_to_type=user|asset|location, assigned_user|assigned_asset|assigned_location,
     * optional checkout_at, expected_checkin, note.
     */
    public function checkout(SimCheckoutRequest $request, Sim $sim): JsonResponse
    {
        $this->authorize('checkout', $sim);

        $target = $this->determineCheckoutTarget();

        try {
            $checkedOut = DB::transaction(function () use ($sim, $target, $request): bool {
                $locked = Sim::whereKey($sim->id)->lockForUpdate()->first();
                if (! $locked || ! $locked->availableForCheckout()) {
                    throw new CheckoutNotAllowed(trans('admin/sims/message.checkout.not_available'));
                }

                if ($request->filled('status_id')) {
                    $locked->status_id = (int) $request->input('status_id');
                }

                return $locked->checkOut(
                    $target,
                    auth()->user(),
                    $request->input('checkout_at') ?: null,
                    $request->input('expected_checkin') ?: null,
                    $request->input('note'),
                );
            });
        } catch (CheckoutNotAllowed $e) {
            return response()->json(Helper::formatStandardApiResponse('error', ['sim' => e($sim->iccid)], $e->getMessage()));
        }

        if ($checkedOut) {
            return response()->json(Helper::formatStandardApiResponse('success', (new SimsTransformer)->transformSim($sim->fresh()), trans('admin/sims/message.checkout.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', ['sim' => e($sim->iccid)], trans('admin/sims/message.checkout.error')));
    }

    public function checkin(SimCheckinRequest $request, Sim $sim): JsonResponse
    {
        $this->authorize('checkin', $sim);

        $checkedIn = DB::transaction(function () use ($sim, $request): bool {
            $locked = Sim::whereKey($sim->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->availableForCheckIn()) {
                return false;
            }

            if ($request->filled('status_id')) {
                $locked->status_id = (int) $request->input('status_id');
            }

            return $locked->checkIn(
                $request->input('note'),
                $request->input('checkin_at') ?: null,
                $request->input('location_id') ? (int) $request->input('location_id') : null,
            );
        });

        if ($checkedIn) {
            return response()->json(Helper::formatStandardApiResponse('success', (new SimsTransformer)->transformSim($sim->fresh()), trans('admin/sims/message.checkin.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', ['sim' => e($sim->iccid)], trans('admin/sims/message.checkin.not_assigned')));
    }

    public function history(Request $request, Sim $sim): JsonResponse|array
    {
        $this->authorize('history', $sim);

        $historyQuery = $sim->getHistory($request);
        $total = (clone $historyQuery)->count();
        $offset = ($request->input('offset') > $total) ? $total : app('api_offset_value');
        $limit = app('api_limit_value');
        $history = (clone $historyQuery)->skip($offset)->take($limit)->get();

        return response()->json((new ActionlogsTransformer)->transformActionlogs($history, $total), 200, ['Content-Type' => 'application/json;charset=utf8'], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Users whose phone/mobile (from AD) matches this SIM's number.
     */
    public function phoneMatches(Sim $sim): JsonResponse
    {
        $this->authorize('view', $sim);
        $this->authorize('view', User::class);

        $users = SimPhoneMatcher::usersForSim($sim)->map(fn (User $user) => [
            'user' => (new SimsTransformer)->transformUserCompact($user),
            'field' => $user->sim_match_field,
            'checked_out_to_user' => $sim->checkedOutToUser() && ((int) $sim->assigned_to === (int) $user->id),
        ])->values();

        return response()->json(Helper::formatStandardApiResponse('success', ['total' => $users->count(), 'rows' => $users]));
    }

    /**
     * SIMs whose number matches this user's phone/mobile.
     */
    public function userPhoneMatches(User $user): JsonResponse
    {
        $this->authorize('index', Sim::class);
        $this->authorize('view', $user);

        $sims = SimPhoneMatcher::simsForUser($user)->map(fn (Sim $sim) => [
            'sim' => (new SimsTransformer)->transformSimCompact($sim),
            'field' => $sim->sim_match_field,
            'checked_out_to_user' => $sim->checkedOutToUser() && ((int) $sim->assigned_to === (int) $user->id),
            'available' => $sim->availableForCheckout(),
        ])->values();

        return response()->json(Helper::formatStandardApiResponse('success', ['total' => $sims->count(), 'rows' => $sims]));
    }

    /**
     * All SIM/user number matches where the SIM isn't checked out to that user.
     */
    public function pendingPhoneMatches(): JsonResponse
    {
        $this->authorize('index', Sim::class);
        $this->authorize('view', User::class);

        $transformer = new SimsTransformer;
        $matches = SimPhoneMatcher::pendingMatches()->map(fn (array $match) => [
            'sim' => $transformer->transformSimCompact($match['sim']),
            'user' => $transformer->transformUserCompact($match['user']),
            'field' => $match['field'],
            'status' => $match['status'],
        ])->values();

        return response()->json(Helper::formatStandardApiResponse('success', ['total' => $matches->count(), 'rows' => $matches]));
    }

    /**
     * Select2 endpoint. Optional available=true limits the list to SIMs
     * that can currently be checked out.
     */
    public function selectlist(Request $request): array
    {
        $this->authorize('view.selectlists');

        $sims = Sim::select(['sims.id', 'sims.name', 'sims.iccid', 'sims.msisdn', 'sims.assigned_to', 'sims.status_id']);

        if ($request->filled('search') && is_string($request->input('search'))) {
            $search = $request->input('search');
            $sims->where(function ($query) use ($search) {
                $query->where('sims.name', 'LIKE', '%'.$search.'%')
                    ->orWhere('sims.iccid', 'LIKE', '%'.$search.'%')
                    ->orWhere('sims.msisdn', 'LIKE', '%'.$search.'%');
            });
        }

        if ($request->boolean('available')) {
            $sims->Available();
        }

        if ($request->filled('companyId')) {
            $sims->where('sims.company_id', (int) $request->input('companyId'));
        }

        $sims = $sims->orderBy('sims.iccid', 'ASC')->paginate(50);

        foreach ($sims as $sim) {
            $label = $sim->msisdn ? $sim->msisdn.' — '.$sim->iccid : $sim->iccid;
            if ($sim->name) {
                $label = $sim->name.' ('.$label.')';
            }
            $sim->use_text = e($label);
        }

        return (new SelectlistTransformer)->transformSelectlist($sims);
    }
}
