<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds SIM cards as a first-class, checkoutable object.
 *
 * A SIM is a single-unit item (no quantity) that can be checked out to a
 * user, an asset (phone, modem, router...) or a location, exactly like an
 * asset, but without the asset tag / model plumbing. The ICCID is the
 * natural unique identifier of a SIM card.
 *
 * sim_plans play the role asset models play for assets: a plan belongs to
 * a mobile operator (supplier) and carries the custom fieldset that applies
 * to every SIM on that plan. settings.sim_fieldset_id is the fallback
 * fieldset for SIMs without a plan.
 *
 * Phone numbers are stored in E.164 (+79001234567) with the ISO country
 * they were entered for. users.phone_e164 / users.mobile_e164 hold the same
 * normalization of the free-text phone / mobile fields (which LDAP sync
 * fills from AD), so SIMs can be matched to their owners with an indexed
 * equality lookup.
 *
 * Secrets (PIN, PUK, eSIM activation code) are stored encrypted at the
 * model layer, hence TEXT columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sim_plans')) {
            Schema::create('sim_plans', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->integer('supplier_id')->nullable();
                $table->integer('fieldset_id')->nullable();
                $table->integer('company_id')->nullable();
                $table->decimal('monthly_cost', 20, 2)->nullable();
                $table->text('notes')->nullable();
                $table->integer('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('supplier_id');
                $table->index('fieldset_id');
                $table->index('company_id');
            });
        }

        if (! Schema::hasTable('sims')) {
            Schema::create('sims', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('iccid', 32);
                $table->string('msisdn', 32)->nullable();
                $table->char('phone_country', 2)->nullable();
                $table->string('imsi', 32)->nullable();
                $table->string('sim_type', 16)->default('physical');
                $table->integer('sim_plan_id')->nullable();
                $table->integer('supplier_id')->nullable();
                $table->string('account_number')->nullable();
                $table->decimal('monthly_cost', 20, 2)->nullable();
                $table->text('pin')->nullable();
                $table->text('puk')->nullable();
                $table->text('activation_code')->nullable();
                $table->integer('status_id')->nullable();
                $table->integer('company_id')->nullable();
                $table->integer('location_id')->nullable();
                $table->integer('assigned_to')->nullable();
                $table->string('assigned_type')->nullable();
                $table->dateTime('last_checkout')->nullable();
                $table->dateTime('last_checkin')->nullable();
                $table->date('expected_checkin')->nullable();
                $table->date('activation_date')->nullable();
                $table->date('expiration_date')->nullable();
                $table->text('notes')->nullable();
                $table->text('custom_fields')->nullable();
                $table->integer('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('iccid');
                $table->index('msisdn');
                $table->index('sim_plan_id');
                $table->index('company_id');
                $table->index('location_id');
                $table->index('supplier_id');
                $table->index('status_id');
                $table->index(['assigned_type', 'assigned_to']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone_e164')) {
                $table->string('phone_e164', 32)->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'mobile_e164')) {
                $table->string('mobile_e164', 32)->nullable()->index();
            }
        });

        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'sim_fieldset_id')) {
                $table->integer('sim_fieldset_id')->nullable();
            }
            if (! Schema::hasColumn('settings', 'sim_default_country')) {
                $table->char('sim_default_country', 2)->nullable();
            }
            if (! Schema::hasColumn('settings', 'sim_match_fields')) {
                // Comma-separated user columns to match SIM numbers against.
                $table->string('sim_match_fields', 32)->nullable()->default('phone,mobile');
            }
            if (! Schema::hasColumn('settings', 'sim_profile_writeback')) {
                // off | empty | always: copy the SIM number into users.mobile on checkout.
                $table->string('sim_profile_writeback', 8)->nullable()->default('off');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            foreach (['sim_fieldset_id', 'sim_default_country', 'sim_match_fields', 'sim_profile_writeback'] as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone_e164')) {
                $table->dropIndex(['phone_e164']);
                $table->dropColumn('phone_e164');
            }
            if (Schema::hasColumn('users', 'mobile_e164')) {
                $table->dropIndex(['mobile_e164']);
                $table->dropColumn('mobile_e164');
            }
        });

        Schema::dropIfExists('sims');
        Schema::dropIfExists('sim_plans');
    }
};
