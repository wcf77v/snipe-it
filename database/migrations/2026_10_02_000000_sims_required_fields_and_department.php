<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SIM cards, second iteration:
 *  - ICCID becomes optional: many SIMs are tracked by phone number only.
 *  - A SIM can belong to a department (taken from the assigned user by default).
 *  - The monthly fee lives on the plan only; the per-SIM column is dropped.
 *    Values set on SIMs are copied to their plan first when the plan has none.
 *  - settings.sim_required_fields lists which SIM form fields are required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sims', function (Blueprint $table) {
            $table->string('iccid', 32)->nullable()->change();

            if (! Schema::hasColumn('sims', 'department_id')) {
                $table->integer('department_id')->nullable()->after('location_id');
                $table->index('department_id');
            }
        });

        if (Schema::hasColumn('sims', 'monthly_cost')) {
            // Keep any fee already entered on a SIM by moving it to its plan.
            $fees = DB::table('sims')
                ->whereNotNull('sim_plan_id')
                ->whereNotNull('monthly_cost')
                ->select('sim_plan_id', DB::raw('MAX(monthly_cost) as fee'))
                ->groupBy('sim_plan_id')
                ->get();

            foreach ($fees as $fee) {
                DB::table('sim_plans')
                    ->where('id', $fee->sim_plan_id)
                    ->whereNull('monthly_cost')
                    ->update(['monthly_cost' => $fee->fee]);
            }

            Schema::table('sims', function (Blueprint $table) {
                $table->dropColumn('monthly_cost');
            });
        }

        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'sim_required_fields')) {
                $table->string('sim_required_fields', 191)->nullable()->default('msisdn,supplier_id');
            }
        });

        DB::table('settings')->whereNull('sim_required_fields')->update(['sim_required_fields' => 'msisdn,supplier_id']);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'sim_required_fields')) {
                $table->dropColumn('sim_required_fields');
            }
        });

        Schema::table('sims', function (Blueprint $table) {
            if (! Schema::hasColumn('sims', 'monthly_cost')) {
                $table->decimal('monthly_cost', 20, 2)->nullable();
            }
            if (Schema::hasColumn('sims', 'department_id')) {
                $table->dropIndex(['department_id']);
                $table->dropColumn('department_id');
            }
        });
    }
};
