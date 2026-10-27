<?php

namespace Tests\Feature\Sims\Ui;

use App\Models\Asset;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\Location;
use App\Models\Sim;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimsUiTest extends TestCase
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

    protected function tearDown(): void
    {
        Sim::flushCustomFieldset();
        parent::tearDown();
    }

    public function test_index_page_renders()
    {
        Sim::factory()->create();

        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('sims.index'))
            ->assertOk();
    }

    public function test_index_requires_permission()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('sims.index'))
            ->assertForbidden();
    }

    public function test_show_page_renders()
    {
        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('sims.show', Sim::factory()->checkedOutToUser()->create()))
            ->assertOk();
    }

    public function test_show_requires_permission()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('sims.show', Sim::factory()->create()))
            ->assertForbidden();
    }

    public function test_show_page_does_not_leak_secrets_without_permission()
    {
        $sim = Sim::factory()->create(['pin' => '4826', 'puk' => '93715284']);

        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('sims.show', $sim))
            ->assertOk()
            ->assertSee(strtoupper(trans('admin/custom_fields/general.encrypted')))
            ->assertDontSee('4826')
            ->assertDontSee('93715284')
            ->assertDontSee('sim-secret-pin-to-show');
    }

    public function test_show_page_includes_secrets_with_permission()
    {
        $sim = Sim::factory()->create(['pin' => '4826']);

        $this->actingAs(User::factory()->viewSims()->viewSimSecrets()->create())
            ->get(route('sims.show', $sim))
            ->assertOk()
            ->assertSee('4826')
            ->assertSee('sim-secret-pin')
            ->assertSee('showHideEncValue')
            ->assertSee(\App\Models\Sim::SECRET_MASK);
    }

    public function test_create_page_renders()
    {
        $this->actingAs(User::factory()->createSims()->create())
            ->get(route('sims.create'))
            ->assertOk();
    }

    public function test_create_page_requires_permission()
    {
        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('sims.create'))
            ->assertForbidden();
    }

    public function test_edit_page_includes_current_secret_with_permission()
    {
        $sim = Sim::factory()->create(['pin' => '4826']);

        $this->actingAs(User::factory()->editSims()->viewSimSecrets()->create())
            ->get(route('sims.edit', $sim))
            ->assertOk()
            ->assertSee('value="4826"', false)
            ->assertSee('js-sim-secret-eye');
    }

    public function test_edit_page_does_not_leak_current_secret_without_permission()
    {
        $sim = Sim::factory()->create(['pin' => '4826']);

        $this->actingAs(User::factory()->editSims()->create())
            ->get(route('sims.edit', $sim))
            ->assertOk()
            ->assertDontSee('4826')
            ->assertDontSee('js-sim-secret-eye')
            ->assertSee(trans('admin/sims/general.secret_is_set'));
    }

    public function test_edit_page_requires_permission()
    {
        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('sims.edit', Sim::factory()->create()))
            ->assertForbidden();
    }

    public function test_clone_page_renders_without_identifiers_or_secrets()
    {
        $sim = Sim::factory()->create(['pin' => '4826']);

        $this->actingAs(User::factory()->viewSims()->createSims()->create())
            ->get(route('sims.clone.create', $sim))
            ->assertOk()
            ->assertDontSee($sim->iccid)
            ->assertDontSee('4826');
    }

    public function test_clone_page_requires_permission()
    {
        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('sims.clone.create', Sim::factory()->create()))
            ->assertForbidden();
    }

    public function test_can_store_sim()
    {
        $this->actingAs(User::factory()->createSims()->create())
            ->post(route('sims.store'), [
                'iccid' => '8970101234567890123',
                'msisdn' => '+79001234567',
                'sim_type' => 'esim',
                'pin' => '1234',
                'redirect_option' => 'index',
            ])
            ->assertRedirect(route('sims.index'))
            ->assertSessionHasNoErrors();

        $sim = Sim::where('iccid', '8970101234567890123')->firstOrFail();
        $this->assertEquals('esim', $sim->sim_type);
        $this->assertEquals('1234', $sim->pin);
    }

    public function test_blank_secret_on_update_keeps_existing_value()
    {
        $sim = Sim::factory()->create(['pin' => '1111']);

        $this->actingAs(User::factory()->editSims()->create())
            ->put(route('sims.update', $sim), [
                'iccid' => $sim->iccid,
                'account_number' => 'ACC-7',
                'pin' => '',
                'redirect_option' => 'item',
            ])
            ->assertSessionHasNoErrors();

        $sim->refresh();
        $this->assertEquals('ACC-7', $sim->account_number);
        $this->assertEquals('1111', $sim->pin);
    }

    public function test_clear_checkbox_removes_secret()
    {
        $sim = Sim::factory()->create(['pin' => '1111']);

        $this->actingAs(User::factory()->editSims()->create())
            ->put(route('sims.update', $sim), [
                'iccid' => $sim->iccid,
                'clear_pin' => '1',
                'redirect_option' => 'item',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($sim->refresh()->pin);
    }

    public function test_checkout_page_renders()
    {
        $this->actingAs(User::factory()->checkoutSims()->create())
            ->get(route('sims.checkout.create', Sim::factory()->create()))
            ->assertOk();
    }

    public function test_checkout_page_requires_permission()
    {
        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('sims.checkout.create', Sim::factory()->create()))
            ->assertForbidden();
    }

    public function test_checkin_page_renders()
    {
        $this->actingAs(User::factory()->checkinSims()->create())
            ->get(route('sims.checkin.create', Sim::factory()->checkedOutToUser()->create()))
            ->assertOk();
    }

    public function test_can_checkout_to_asset_via_ui()
    {
        $sim = Sim::factory()->create();
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->checkoutSims()->create())
            ->post(route('sims.checkout.store', $sim), [
                'checkout_to_type' => 'asset',
                'assigned_asset' => $asset->id,
                'redirect_option' => 'item',
            ])
            ->assertRedirect(route('sims.show', $sim));

        $this->assertTrue($sim->refresh()->checkedOutToAsset());
        $this->assertEquals($asset->id, $sim->assigned_to);
    }

    public function test_can_checkin_via_ui()
    {
        $location = Location::factory()->create();
        $sim = Sim::factory()->checkedOutToUser()->create();

        $this->actingAs(User::factory()->checkinSims()->create())
            ->post(route('sims.checkin.store', $sim), [
                'location_id' => $location->id,
                'redirect_option' => 'item',
            ])
            ->assertRedirect(route('sims.show', $sim));

        $sim->refresh();
        $this->assertNull($sim->assigned_to);
        $this->assertEquals($location->id, $sim->location_id);
    }

    public function test_cannot_delete_checked_out_sim()
    {
        $sim = Sim::factory()->checkedOutToUser()->create();

        $this->actingAs(User::factory()->deleteSims()->create())
            ->delete(route('sims.destroy', $sim))
            ->assertRedirect(route('sims.show', $sim));

        $this->assertNotSoftDeleted($sim);
    }

    public function test_settings_page_requires_admin()
    {
        $this->actingAs(User::factory()->viewSims()->createSims()->create())
            ->get(route('sims.settings.edit'))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('sims.settings.edit'))
            ->assertOk();
    }

    public function test_custom_fields_from_sim_fieldset_are_stored_and_encrypted()
    {
        $plain = CustomField::factory()->create(['name' => 'Data limit', 'field_encrypted' => '0', 'format' => 'ANY']);
        $secret = CustomField::factory()->create(['name' => 'Portal password', 'field_encrypted' => '1', 'format' => 'ANY']);
        $fieldset = CustomFieldset::factory()->create();
        $fieldset->fields()->attach($plain, ['order' => 1, 'required' => false]);
        $fieldset->fields()->attach($secret, ['order' => 2, 'required' => false]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('sims.settings.update'), ['sim_fieldset_id' => $fieldset->id, 'sim_profile_writeback' => 'off', 'sim_default_country' => 'RU'])
            ->assertRedirect(route('sims.settings.edit'));

        Sim::flushCustomFieldset();

        $this->actingAs(User::factory()->createSims()->viewSimSecrets()->create())
            ->post(route('sims.store'), [
                'iccid' => '8970101234567890999',
                $plain->db_column => '10 GB',
                $secret->db_column => 's3cr3t-value',
                'redirect_option' => 'index',
            ])
            ->assertSessionHasNoErrors();

        $sim = Sim::where('iccid', '8970101234567890999')->firstOrFail();
        $this->assertEquals('10 GB', $sim->customFieldValue($plain->fresh()));
        $this->assertEquals('s3cr3t-value', $sim->customFieldValue($secret->fresh()));

        $raw = DB::table('sims')->where('id', $sim->id)->value('custom_fields');
        $this->assertStringContainsString('10 GB', $raw);
        $this->assertStringNotContainsString('s3cr3t-value', $raw);
    }

    public function test_user_view_page_renders_with_sim_tab()
    {
        $user = User::factory()->create();
        Sim::factory()->checkedOutToUser($user)->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('users.show', $user))
            ->assertOk();
    }

    public function test_asset_view_page_renders_with_sim_tab()
    {
        $asset = Asset::factory()->create();
        Sim::factory()->checkedOutToAsset($asset)->create();

        $this->actingAs(User::factory()->superuser()->create())
            ->get(route('hardware.show', $asset))
            ->assertOk();
    }
}
