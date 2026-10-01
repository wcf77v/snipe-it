<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SIM cards, third iteration:
 *  - settings.sim_checkout_email: email the user when a SIM is checked out
 *    to / in from them (SIMs have no category, which is where assets keep
 *    this switch).
 *  - Every SIM gets a status like assets do: SIMs without one get the
 *    default status label (falling back to the first deployable one), so
 *    lists can show Deployed / Ready to Deploy consistently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'sim_checkout_email')) {
                $table->boolean('sim_checkout_email')->default(false);
            }
        });

        $defaultStatusId = DB::table('status_labels')->whereNull('deleted_at')->where('default_label', 1)->value('id')
            ?? DB::table('status_labels')->whereNull('deleted_at')->where('deployable', 1)->where('archived', 0)->orderBy('id')->value('id');

        if ($defaultStatusId) {
            DB::table('sims')->whereNull('status_id')->update(['status_id' => $defaultStatusId]);
        }
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'sim_checkout_email')) {
                $table->dropColumn('sim_checkout_email');
            }
        });
    }
};
