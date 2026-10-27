<?php

namespace Tests\Feature\Sims\Api;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Location;
use App\Models\Sim;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimsApiTest extends TestCase
{
    /**
     * These tests cover behavior independent of the configurable required
     * fields; SimRequiredFieldsTest covers those.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->settings->set(['sim_required_fields' => '']);
    }

    public function test_index_requires_permission()
    {
        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.sims.index'))
            ->assertForbidden();
    }

    public function test_index_lists_sims()
    {
        $sims = Sim::factory()->count(2)->create();

        $this->actingAsForApi(User::factory()->viewSims()->create())
            ->getJson(route('api.sims.index'))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertResponseContainsInRows($sims, 'iccid');
    }

    public function test_index_hides_secrets_without_permission()
    {
        Sim::factory()->create(['pin' => '1234', 'puk' => '12345678']);

        $response = $this->actingAsForApi(User::factory()->viewSims()->create())
            ->getJson(route('api.sims.index'))
            ->assertOk();

        $this->assertArrayNotHasKey('pin', $response['rows'][0]);
        $this->assertArrayNotHasKey('puk', $response['rows'][0]);
        $this->assertStringNotContainsString('12345678', $response->getContent());
    }

    public function test_index_shows_secrets_with_permission()
    {
        Sim::factory()->create(['pin' => '1234', 'puk' => '12345678']);

        $this->actingAsForApi(User::factory()->viewSims()->viewSimSecrets()->create())
            ->getJson(route('api.sims.index'))
            ->assertOk()
            ->assertJsonPath('rows.0.pin', '1234')
            ->assertJsonPath('rows.0.puk', '12345678');
    }

    public function test_index_filters_by_assignment()
    {
        $user = User::factory()->create();
        $assigned = Sim::factory()->checkedOutToUser($user)->create();
        $other = Sim::factory()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.sims.index', ['assigned_to' => $user->id, 'assigned_type' => 'user']))
            ->assertOk()
            ->assertResponseContainsInRows($assigned, 'iccid')
            ->assertResponseDoesNotContainInRows($other, 'iccid');
    }

    public function test_index_rejects_unknown_assigned_type()
    {
        Sim::factory()->checkedOutToUser()->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.sims.index', ['assigned_to' => 1, 'assigned_type' => 'App\\Models\\Setting']))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_index_adheres_to_company_scoping()
    {
        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $simA = Sim::factory()->for($companyA)->create();
        $simB = Sim::factory()->for($companyB)->create();

        $userInCompanyA = $companyA->users()->save(User::factory()->viewSims()->make());

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($userInCompanyA)
            ->getJson(route('api.sims.index'))
            ->assertResponseContainsInRows($simA, 'iccid')
            ->assertResponseDoesNotContainInRows($simB, 'iccid');
    }

    public function test_can_create_sim_and_secrets_are_encrypted_at_rest()
    {
        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), [
                'iccid' => '8970 1012 3456 7890 123',
                'msisdn' => '+7 900 123-45-67',
                'pin' => '4321',
                'puk' => '87654321',
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $sim = Sim::where('iccid', '8970101234567890123')->firstOrFail();
        $this->assertEquals('4321', $sim->pin);
        $this->assertEquals('87654321', $sim->puk);

        $raw = DB::table('sims')->where('id', $sim->id)->first();
        $this->assertNotEquals('4321', $raw->pin);
        $this->assertNotEquals('87654321', $raw->puk);

        $this->assertDatabaseHas('action_logs', [
            'item_type' => Sim::class,
            'item_id' => $sim->id,
            'action_type' => 'create',
        ]);
    }

    public function test_create_requires_permission()
    {
        $this->actingAsForApi(User::factory()->viewSims()->create())
            ->postJson(route('api.sims.store'), ['iccid' => '8970101234567890123'])
            ->assertForbidden();

        $this->assertDatabaseMissing('sims', ['iccid' => '8970101234567890123']);
    }

    public function test_iccid_is_validated()
    {
        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), ['iccid' => 'not-an-iccid'])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertMessagesContains('iccid');
    }

    public function test_pin_format_is_validated()
    {
        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), ['iccid' => '8970101234567890123', 'pin' => '12'])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertMessagesContains('pin');
    }

    public function test_iccid_must_be_unique()
    {
        Sim::factory()->create(['iccid' => '8970101234567890123']);

        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), ['iccid' => '8970101234567890123'])
            ->assertOk()
            ->assertStatusMessageIs('error');

        $this->assertEquals(1, Sim::where('iccid', '8970101234567890123')->count());
    }

    public function test_partial_update_keeps_secrets_that_were_not_sent()
    {
        $sim = Sim::factory()->create(['pin' => '1111', 'puk' => '22222222']);

        $this->actingAsForApi(User::factory()->editSims()->create())
            ->patchJson(route('api.sims.update', $sim), ['account_number' => 'ACC-42'])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $sim->refresh();
        $this->assertEquals('ACC-42', $sim->account_number);
        $this->assertEquals('1111', $sim->pin);
        $this->assertEquals('22222222', $sim->puk);
    }

    public function test_update_history_never_contains_clear_text_secrets()
    {
        $sim = Sim::factory()->create(['pin' => '1111']);

        $this->actingAsForApi(User::factory()->editSims()->create())
            ->patchJson(route('api.sims.update', $sim), ['pin' => '9999'])
            ->assertOk();

        $log = Actionlog::where('item_type', Sim::class)
            ->where('item_id', $sim->id)
            ->where('action_type', 'update')
            ->firstOrFail();

        $this->assertStringNotContainsString('1111', $log->log_meta);
        $this->assertStringNotContainsString('9999', $log->log_meta);
        $this->assertStringContainsString(Sim::SECRET_MASK, $log->log_meta);
    }

    public function test_can_checkout_to_user_and_checkin()
    {
        $sim = Sim::factory()->create();
        $target = User::factory()->create();
        $admin = User::factory()->checkoutSims()->checkinSims()->create();

        $this->actingAsForApi($admin)
            ->postJson(route('api.sims.checkout', $sim), [
                'checkout_to_type' => 'user',
                'assigned_user' => $target->id,
                'note' => 'issued',
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $sim->refresh();
        $this->assertTrue($sim->checkedOutToUser());
        $this->assertEquals($target->id, $sim->assigned_to);
        $this->assertDatabaseHas('action_logs', [
            'item_type' => Sim::class,
            'item_id' => $sim->id,
            'action_type' => 'checkout',
            'target_type' => User::class,
            'target_id' => $target->id,
        ]);

        // Show must resolve the assignee without an index-style eager load.
        $this->actingAsForApi(User::factory()->viewSims()->create())
            ->getJson(route('api.sims.show', $sim))
            ->assertOk()
            ->assertJsonPath('assigned_to.id', $target->id)
            ->assertJsonPath('assigned_to.type', 'user');

        $this->actingAsForApi($admin)
            ->postJson(route('api.sims.checkin', $sim), ['note' => 'returned'])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $sim->refresh();
        $this->assertNull($sim->assigned_to);
        $this->assertNull($sim->assigned_type);
        $this->assertDatabaseHas('action_logs', [
            'item_type' => Sim::class,
            'item_id' => $sim->id,
            'action_type' => 'checkin from',
        ]);
        $this->assertDatabaseMissing('action_logs', [
            'item_type' => Sim::class,
            'item_id' => $sim->id,
            'action_type' => 'force checkin',
        ]);
    }

    public function test_checkin_without_eager_load_is_not_force_checkin()
    {
        $owner = User::factory()->create();
        $sim = Sim::factory()->checkedOutToUser($owner)->create();
        $admin = User::factory()->checkinSims()->create();

        // Fresh model, no assignedTo relation loaded — previously this
        // took the orphan/force-checkin path and broke rebind history.
        $bare = Sim::findOrFail($sim->id);
        $this->assertFalse($bare->relationLoaded('assignedTo'));
        $this->assertNotNull($bare->assignedTarget());

        $this->actingAs($admin);
        $this->assertTrue($bare->checkIn('returned bare'));

        $this->assertNull($bare->fresh()->assigned_to);
        $this->assertDatabaseHas('action_logs', [
            'item_type' => Sim::class,
            'item_id' => $sim->id,
            'action_type' => 'checkin from',
            'target_type' => User::class,
            'target_id' => $owner->id,
        ]);
        $this->assertDatabaseMissing('action_logs', [
            'item_type' => Sim::class,
            'item_id' => $sim->id,
            'action_type' => 'force checkin',
        ]);
    }

    public function test_checkout_copies_user_department()
    {
        $department = \App\Models\Department::factory()->create();
        $owner = User::factory()->create(['department_id' => $department->id]);
        $sim = Sim::factory()->create(['department_id' => null]);

        $this->actingAsForApi(User::factory()->checkoutSims()->create())
            ->postJson(route('api.sims.checkout', $sim), [
                'checkout_to_type' => 'user',
                'assigned_user' => $owner->id,
            ])
            ->assertStatusMessageIs('success');

        $this->assertEquals($department->id, $sim->fresh()->department_id);
    }

    public function test_can_checkout_to_asset_and_location()
    {
        $admin = User::factory()->checkoutSims()->create();

        $asset = Asset::factory()->create();
        $simForAsset = Sim::factory()->create();
        $this->actingAsForApi($admin)
            ->postJson(route('api.sims.checkout', $simForAsset), [
                'checkout_to_type' => 'asset',
                'assigned_asset' => $asset->id,
            ])
            ->assertStatusMessageIs('success');
        $this->assertTrue($simForAsset->refresh()->checkedOutToAsset());
        $this->assertEquals(1, $asset->assignedSims()->count());

        $location = Location::factory()->create();
        $simForLocation = Sim::factory()->create();
        $this->actingAsForApi($admin)
            ->postJson(route('api.sims.checkout', $simForLocation), [
                'checkout_to_type' => 'location',
                'assigned_location' => $location->id,
            ])
            ->assertStatusMessageIs('success');
        $simForLocation->refresh();
        $this->assertTrue($simForLocation->checkedOutToLocation());
        $this->assertEquals($location->id, $simForLocation->location_id);
    }

    public function test_cannot_checkout_a_sim_that_is_already_checked_out()
    {
        $sim = Sim::factory()->checkedOutToUser()->create();
        $other = User::factory()->create();

        $this->actingAsForApi(User::factory()->checkoutSims()->create())
            ->postJson(route('api.sims.checkout', $sim), [
                'checkout_to_type' => 'user',
                'assigned_user' => $other->id,
            ])
            ->assertStatusMessageIs('error');

        $this->assertNotEquals($other->id, $sim->refresh()->assigned_to);
    }

    public function test_cannot_checkout_sim_with_undeployable_status()
    {
        $sim = Sim::factory()->create(['status_id' => Statuslabel::factory()->archived()->create()->id]);

        $this->actingAsForApi(User::factory()->checkoutSims()->create())
            ->postJson(route('api.sims.checkout', $sim), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
            ])
            ->assertStatusMessageIs('error');

        $this->assertNull($sim->refresh()->assigned_to);
    }

    public function test_cannot_checkout_to_deleted_user()
    {
        $sim = Sim::factory()->create();
        $deleted = User::factory()->deletedUser()->create();

        $this->actingAsForApi(User::factory()->checkoutSims()->create())
            ->postJson(route('api.sims.checkout', $sim), [
                'checkout_to_type' => 'user',
                'assigned_user' => $deleted->id,
            ])
            ->assertStatusMessageIs('error')
            ->assertMessagesContains('assigned_user');

        $this->assertNull($sim->refresh()->assigned_to);
    }

    public function test_checkout_requires_permission()
    {
        $sim = Sim::factory()->create();

        $this->actingAsForApi(User::factory()->viewSims()->create())
            ->postJson(route('api.sims.checkout', $sim), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
            ])
            ->assertForbidden();
    }

    public function test_cannot_delete_checked_out_sim()
    {
        $sim = Sim::factory()->checkedOutToUser()->create();

        $this->actingAsForApi(User::factory()->deleteSims()->create())
            ->deleteJson(route('api.sims.destroy', $sim))
            ->assertStatusMessageIs('error');

        $this->assertNotSoftDeleted($sim);
    }

    public function test_can_delete_and_restore_sim()
    {
        $sim = Sim::factory()->create();
        $admin = User::factory()->deleteSims()->createSims()->viewSims()->create();

        $this->actingAsForApi($admin)
            ->deleteJson(route('api.sims.destroy', $sim))
            ->assertStatusMessageIs('success');
        $this->assertSoftDeleted($sim);

        $this->actingAsForApi($admin)
            ->postJson(route('api.sims.restore', $sim->id))
            ->assertStatusMessageIs('success');
        $this->assertNotSoftDeleted($sim);
    }

    public function test_can_look_up_sim_by_iccid()
    {
        $sim = Sim::factory()->create(['iccid' => '8970101234567890123']);

        $this->actingAsForApi(User::factory()->viewSims()->create())
            ->getJson(route('api.sims.show.byiccid', '8970-1012-3456-7890-123'))
            ->assertOk()
            ->assertJsonPath('id', $sim->id);
    }

    public function test_history_lists_checkouts()
    {
        $sim = Sim::factory()->create();
        $admin = User::factory()->superuser()->create();

        $this->actingAsForApi($admin)
            ->postJson(route('api.sims.checkout', $sim), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
            ]);

        $this->actingAsForApi($admin)
            ->getJson(route('api.sims.history', $sim))
            ->assertOk()
            ->assertJsonFragment(['action_type' => 'checkout']);
    }

    public function test_user_with_checked_out_sim_cannot_be_deleted()
    {
        $user = User::factory()->create();
        Sim::factory()->checkedOutToUser($user)->create();

        $this->assertFalse($user->hasNoAssignmentBlockers());
    }
}
