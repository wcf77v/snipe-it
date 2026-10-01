<?php

namespace Tests\Feature\Sims;

use App\Models\Sim;
use App\Models\User;
use App\Services\PhoneNumberService;
use App\Services\SimPhoneMatcher;
use Tests\TestCase;

class SimPhoneMatchingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->settings->set([
            'sim_default_country' => 'RU',
            'sim_match_fields' => 'phone,mobile',
            'sim_profile_writeback' => 'off',
            'sim_required_fields' => '',
        ]);
    }

    public function test_phone_numbers_are_normalized_per_country()
    {
        $this->assertEquals('+79001234567', PhoneNumberService::toE164('8 (900) 123-45-67', 'RU'));
        $this->assertEquals('+79001234567', PhoneNumberService::toE164('+7 900 1234567', 'DE'));
        $this->assertEquals('+77011234567', PhoneNumberService::toE164('8 701 123 45 67', 'KZ'));
        $this->assertNull(PhoneNumberService::toE164('12345', 'RU'));
    }

    public function test_sim_number_is_stored_in_e164_for_selected_country()
    {
        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), [
                'iccid' => '8970101234567890123',
                'phone_country' => 'RU',
                'msisdn' => '8 (900) 123-45-67',
            ])
            ->assertStatusMessageIs('success');

        $sim = Sim::where('iccid', '8970101234567890123')->firstOrFail();
        $this->assertEquals('+79001234567', $sim->msisdn);
        $this->assertEquals('RU', $sim->phone_country);
    }

    public function test_invalid_number_for_country_is_rejected()
    {
        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), [
                'iccid' => '8970101234567890123',
                'phone_country' => 'RU',
                'msisdn' => '12345',
            ])
            ->assertStatusMessageIs('error')
            ->assertMessagesContains('msisdn');
    }

    public function test_user_numbers_from_ad_fields_are_normalized_on_save()
    {
        $user = User::factory()->create([
            'phone' => '+7 (495) 123-45-67',
            'mobile' => '8 900 123-45-67',
            'country' => 'RU',
        ]);

        $user->refresh();
        $this->assertEquals('+74951234567', $user->phone_e164);
        $this->assertEquals('+79001234567', $user->mobile_e164);
    }

    public function test_sim_is_matched_to_user_by_mobile()
    {
        $owner = User::factory()->create(['mobile' => '8 900 123-45-67', 'country' => 'RU']);
        User::factory()->create(['mobile' => '8 900 765-43-21', 'country' => 'RU']);
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);

        $matches = SimPhoneMatcher::usersForSim($sim);

        $this->assertCount(1, $matches);
        $this->assertEquals($owner->id, $matches->first()->id);
        $this->assertEquals('mobile', $matches->first()->sim_match_field);
    }

    public function test_user_is_matched_to_sims_by_phone()
    {
        $user = User::factory()->create(['phone' => '+7 900 1234567']);
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);
        Sim::factory()->create(['msisdn' => '+79009999999']);

        $matches = SimPhoneMatcher::simsForUser($user->refresh());

        $this->assertCount(1, $matches);
        $this->assertEquals($sim->id, $matches->first()->id);
    }

    public function test_disabled_match_fields_are_ignored()
    {
        $this->settings->set(['sim_match_fields' => 'mobile']);

        User::factory()->create(['phone' => '+79001234567']);
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);

        $this->assertCount(0, SimPhoneMatcher::usersForSim($sim));
    }

    public function test_pending_matches_exclude_sims_already_checked_out_to_owner()
    {
        $owner = User::factory()->create(['mobile' => '+79001234567']);
        Sim::factory()->checkedOutToUser($owner)->create(['msisdn' => '+79001234567']);

        $other = User::factory()->create(['mobile' => '+79007654321']);
        $unlinked = Sim::factory()->create(['msisdn' => '+79007654321']);

        $matches = SimPhoneMatcher::pendingMatches();

        $this->assertCount(1, $matches);
        $this->assertEquals($unlinked->id, $matches->first()['sim']->id);
        $this->assertEquals($other->id, $matches->first()['user']->id);
        $this->assertEquals('available', $matches->first()['status']);
    }

    public function test_matches_page_renders_and_links_selected_sims()
    {
        $owner = User::factory()->create(['mobile' => '+79001234567']);
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);
        $admin = User::factory()->viewSims()->checkoutSims()->viewUsers()->create();

        $this->actingAs($admin)
            ->get(route('sims.matches.index'))
            ->assertOk()
            ->assertSee($sim->iccid);

        $this->actingAs($admin)
            ->post(route('sims.matches.store'), ['matches' => [$sim->id.':'.$owner->id]])
            ->assertRedirect(route('sims.matches.index'));

        $sim->refresh();
        $this->assertTrue($sim->checkedOutToUser());
        $this->assertEquals($owner->id, $sim->assigned_to);
    }

    public function test_matches_page_requires_permission_to_view_users()
    {
        $this->actingAs(User::factory()->viewSims()->checkoutSims()->create())
            ->get(route('sims.matches.index'))
            ->assertForbidden();
    }

    public function test_sim_page_hides_phone_owner_without_user_view_permission()
    {
        User::factory()->create(['mobile' => '+79001234567', 'first_name' => 'Secretowner']);
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);

        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('sims.show', $sim))
            ->assertOk()
            ->assertDontSee('Secretowner');
    }

    public function test_bulk_link_rejects_pairs_that_do_not_match()
    {
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);
        $stranger = User::factory()->create(['mobile' => '+79005555555']);

        $this->actingAs(User::factory()->viewSims()->checkoutSims()->create())
            ->post(route('sims.matches.store'), ['matches' => [$sim->id.':'.$stranger->id]]);

        $this->assertNull($sim->refresh()->assigned_to);
    }

    public function test_bulk_link_requires_checkout_permission()
    {
        $owner = User::factory()->create(['mobile' => '+79001234567']);
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);

        $this->actingAs(User::factory()->viewSims()->create())
            ->post(route('sims.matches.store'), ['matches' => [$sim->id.':'.$owner->id]])
            ->assertForbidden();

        $this->assertNull($sim->refresh()->assigned_to);
    }

    public function test_checkout_form_preselects_matched_user()
    {
        $owner = User::factory()->create(['mobile' => '+79001234567', 'first_name' => 'Matchy']);
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);

        $this->actingAs(User::factory()->checkoutSims()->create())
            ->get(route('sims.checkout.create', $sim))
            ->assertOk()
            ->assertSee('value="'.$owner->id.'" selected="selected"', false);
    }

    public function test_writeback_fills_empty_mobile_on_checkout()
    {
        $this->settings->set(['sim_profile_writeback' => 'empty']);

        $user = User::factory()->create(['mobile' => null]);
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);

        $this->actingAsForApi(User::factory()->checkoutSims()->editUsers()->create())
            ->postJson(route('api.sims.checkout', $sim), ['checkout_to_type' => 'user', 'assigned_user' => $user->id])
            ->assertStatusMessageIs('success');

        $this->assertEquals('+79001234567', $user->refresh()->mobile_e164);
    }

    public function test_writeback_requires_permission_to_edit_the_user()
    {
        $this->settings->set(['sim_profile_writeback' => 'always']);

        $user = User::factory()->create(['mobile' => null]);
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);

        $this->actingAsForApi(User::factory()->checkoutSims()->create())
            ->postJson(route('api.sims.checkout', $sim), ['checkout_to_type' => 'user', 'assigned_user' => $user->id])
            ->assertStatusMessageIs('success');

        $this->assertNull($user->refresh()->mobile_e164);
    }

    public function test_writeback_empty_mode_keeps_existing_mobile()
    {
        $this->settings->set(['sim_profile_writeback' => 'empty']);

        $user = User::factory()->create(['mobile' => '+79005555555']);
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);

        $this->actingAsForApi(User::factory()->checkoutSims()->editUsers()->create())
            ->postJson(route('api.sims.checkout', $sim), ['checkout_to_type' => 'user', 'assigned_user' => $user->id]);

        $this->assertEquals('+79005555555', $user->refresh()->mobile_e164);
    }

    public function test_api_lists_phone_matches_for_sim_and_user()
    {
        $owner = User::factory()->create(['mobile' => '+79001234567']);
        $sim = Sim::factory()->create(['msisdn' => '+79001234567']);
        $admin = User::factory()->superuser()->create();

        $this->actingAsForApi($admin)
            ->getJson(route('api.sims.phone-matches.sim', $sim))
            ->assertOk()
            ->assertJsonPath('payload.rows.0.user.id', $owner->id)
            ->assertJsonPath('payload.rows.0.field', 'mobile');

        $this->actingAsForApi($admin)
            ->getJson(route('api.sims.phone-matches.user', $owner))
            ->assertOk()
            ->assertJsonPath('payload.rows.0.sim.id', $sim->id);
    }

    public function test_normalize_command_backfills_users()
    {
        $user = User::factory()->create(['mobile' => '8 900 123 45 67']);
        \DB::table('users')->where('id', $user->id)->update(['mobile_e164' => null]);

        $this->artisan('snipeit:sims-normalize-phones')->assertSuccessful();

        $this->assertEquals('+79001234567', $user->refresh()->mobile_e164);
    }
}
