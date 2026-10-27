<?php

use App\Http\Controllers\Sims;
use App\Models\Sim;
use App\Models\SimPlan;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

/*
 * SIM cards
 *
 * {sim} is always a numeric auto-increment PK; constrain it at the router so
 * garbage input 404s before reaching route-model binding or breadcrumbs.
 */
Route::group(['prefix' => 'sims', 'middleware' => ['auth']], function () {

    Route::get('/', [Sims\SimsController::class, 'index'])
        ->name('sims.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')
            ->push(trans('admin/sims/general.sims'), route('sims.index'))
        );

    Route::get('create', [Sims\SimsController::class, 'create'])
        ->name('sims.create')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('sims.index')
            ->push(trans('general.create'), route('sims.create'))
        );

    Route::post('/', [Sims\SimsController::class, 'store'])
        ->name('sims.store');

    Route::get('settings', [Sims\SimSettingsController::class, 'edit'])
        ->name('sims.settings.edit')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('sims.index')
            ->push(trans('admin/sims/general.settings'), route('sims.settings.edit'))
        );

    Route::post('settings', [Sims\SimSettingsController::class, 'update'])
        ->name('sims.settings.update');

    Route::post('settings/normalize-phones', [Sims\SimSettingsController::class, 'normalize'])
        ->name('sims.settings.normalize');

    Route::get('custom-fields', [Sims\SimsController::class, 'customFields'])
        ->name('sims.custom-fields');

    Route::get('import', [Sims\SimImportController::class, 'create'])
        ->name('sims.import.create')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('sims.index')
            ->push(trans('admin/sims/import.title'), route('sims.import.create'))
        );

    Route::post('import', [Sims\SimImportController::class, 'store'])
        ->name('sims.import.store');

    Route::get('import/template', [Sims\SimImportController::class, 'template'])
        ->name('sims.import.template');

    Route::get('matches', [Sims\SimMatchesController::class, 'index'])
        ->name('sims.matches.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('sims.index')
            ->push(trans('admin/sims/general.matches'), route('sims.matches.index'))
        );

    Route::post('matches', [Sims\SimMatchesController::class, 'store'])
        ->name('sims.matches.store');

    Route::get('{sim}', [Sims\SimsController::class, 'show'])
        ->where('sim', '[0-9]+')
        ->name('sims.show')
        ->breadcrumbs(fn (Trail $trail, Sim $sim) => $trail->parent('sims.index')
            ->push($sim->display_name, route('sims.show', $sim))
        );

    Route::get('{sim}/edit', [Sims\SimsController::class, 'edit'])
        ->where('sim', '[0-9]+')
        ->name('sims.edit')
        ->breadcrumbs(fn (Trail $trail, Sim $sim) => $trail->parent('sims.show', $sim)
            ->push(trans('general.update'))
        );

    Route::put('{sim}', [Sims\SimsController::class, 'update'])
        ->where('sim', '[0-9]+')
        ->name('sims.update');

    Route::delete('{sim}', [Sims\SimsController::class, 'destroy'])
        ->where('sim', '[0-9]+')
        ->name('sims.destroy');

    Route::post('{simId}/restore', [Sims\SimsController::class, 'restore'])
        ->where('simId', '[0-9]+')
        ->name('sims.restore');

    Route::get('{sim}/clone', [Sims\SimsController::class, 'getClone'])
        ->where('sim', '[0-9]+')
        ->name('sims.clone.create')
        ->breadcrumbs(fn (Trail $trail, Sim $sim) => $trail->parent('sims.show', $sim)
            ->push(trans('general.clone'))
        );

    Route::get('{sim}/checkout', [Sims\SimCheckoutController::class, 'create'])
        ->where('sim', '[0-9]+')
        ->name('sims.checkout.create')
        ->breadcrumbs(fn (Trail $trail, Sim $sim) => $trail->parent('sims.show', $sim)
            ->push(trans('general.checkout'))
        );

    Route::post('{sim}/checkout', [Sims\SimCheckoutController::class, 'store'])
        ->where('sim', '[0-9]+')
        ->name('sims.checkout.store');

    Route::get('{sim}/checkin', [Sims\SimCheckinController::class, 'create'])
        ->where('sim', '[0-9]+')
        ->name('sims.checkin.create')
        ->breadcrumbs(fn (Trail $trail, Sim $sim) => $trail->parent('sims.show', $sim)
            ->push(trans('general.checkin'))
        );

    Route::post('{sim}/checkin', [Sims\SimCheckinController::class, 'store'])
        ->where('sim', '[0-9]+')
        ->name('sims.checkin.store');
});

/*
 * SIM plans (tariffs / SIM types). {simplan} is a numeric PK.
 */
Route::group(['prefix' => 'simplans', 'middleware' => ['auth']], function () {

    Route::get('/', [Sims\SimPlansController::class, 'index'])
        ->name('simplans.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('sims.index')
            ->push(trans('admin/sims/general.plans'), route('simplans.index'))
        );

    Route::get('create', [Sims\SimPlansController::class, 'create'])
        ->name('simplans.create')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('simplans.index')
            ->push(trans('general.create'), route('simplans.create'))
        );

    Route::post('/', [Sims\SimPlansController::class, 'store'])
        ->name('simplans.store');

    Route::get('{simplan}', [Sims\SimPlansController::class, 'show'])
        ->where('simplan', '[0-9]+')
        ->name('simplans.show')
        ->breadcrumbs(fn (Trail $trail, SimPlan $simplan) => $trail->parent('simplans.index')
            ->push($simplan->name, route('simplans.show', $simplan))
        );

    Route::get('{simplan}/edit', [Sims\SimPlansController::class, 'edit'])
        ->where('simplan', '[0-9]+')
        ->name('simplans.edit')
        ->breadcrumbs(fn (Trail $trail, SimPlan $simplan) => $trail->parent('simplans.show', $simplan)
            ->push(trans('general.update'))
        );

    Route::put('{simplan}', [Sims\SimPlansController::class, 'update'])
        ->where('simplan', '[0-9]+')
        ->name('simplans.update');

    Route::delete('{simplan}', [Sims\SimPlansController::class, 'destroy'])
        ->where('simplan', '[0-9]+')
        ->name('simplans.destroy');
});
