<?php

namespace Tests\Feature\Sims;

use App\Mail\CheckinSimMail;
use App\Mail\CheckoutSimMail;
use App\Models\CalendarEvent;
use App\Models\Sim;
use App\Models\Statuslabel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SimIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->settings->set(['sim_required_fields' => '', 'sim_checkout_email' => false]);
    }

    public function test_new_sim_gets_the_default_status_and_is_ready_to_deploy()
    {
        $rtd = Statuslabel::factory()->rtd()->create(['default_label' => 1]);

        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), ['msisdn' => '+375291234567', 'supplier_id' => Supplier::factory()->create()->id])
            ->assertStatusMessageIs('success')
            ->assertJsonPath('payload.status.id', $rtd->id)
            ->assertJsonPath('payload.status.status_meta', 'deployable');
    }

    public function test_status_meta_is_deployed_while_checked_out()
    {
        $sim = Sim::factory()->checkedOutToUser()->create(['status_id' => Statuslabel::factory()->rtd()->create()->id]);

        $this->assertEquals('deployed', $sim->statusMeta());

        $this->actingAsForApi(User::factory()->viewSims()->create())
            ->getJson(route('api.sims.show', $sim))
            ->assertJsonPath('status.status_meta', 'deployed');
    }

    public function test_status_type_filter_works_like_assets()
    {
        $rtd = Statuslabel::factory()->rtd()->create();
        $archived = Statuslabel::factory()->archived()->create();
        $deployed = Sim::factory()->checkedOutToUser()->create(['status_id' => $rtd->id]);
        $ready = Sim::factory()->create(['status_id' => $rtd->id]);
        $old = Sim::factory()->create(['status_id' => $archived->id]);
        $admin = User::factory()->viewSims()->create();

        $this->actingAsForApi($admin)->getJson(route('api.sims.index', ['status_type' => 'Deployed']))
            ->assertResponseContainsInRows($deployed, 'iccid')
            ->assertResponseDoesNotContainInRows($ready, 'iccid');

        $this->actingAsForApi($admin)->getJson(route('api.sims.index', ['status_type' => 'RTD']))
            ->assertResponseContainsInRows($ready, 'iccid')
            ->assertResponseDoesNotContainInRows($deployed, 'iccid')
            ->assertResponseDoesNotContainInRows($old, 'iccid');

        $this->actingAsForApi($admin)->getJson(route('api.sims.index', ['status_type' => 'Archived']))
            ->assertResponseContainsInRows($old, 'iccid');
    }

    public function test_checkout_can_set_a_deployable_status_and_checkin_any_status()
    {
        $rtd = Statuslabel::factory()->rtd()->create();
        $broken = Statuslabel::factory()->broken()->create(['deployable' => 0]);
        $sim = Sim::factory()->create(['status_id' => $rtd->id]);
        $admin = User::factory()->checkoutSims()->checkinSims()->create();

        $this->actingAsForApi($admin)
            ->postJson(route('api.sims.checkout', $sim), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
                'status_id' => $broken->id,
            ])
            ->assertStatusMessageIs('error')
            ->assertMessagesContains('status_id');

        $this->actingAsForApi($admin)
            ->postJson(route('api.sims.checkout', $sim), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
            ])
            ->assertStatusMessageIs('success');

        $this->actingAsForApi($admin)
            ->postJson(route('api.sims.checkin', $sim), ['status_id' => $broken->id])
            ->assertStatusMessageIs('success');

        $this->assertEquals($broken->id, $sim->refresh()->status_id);
    }

    public function test_contract_end_and_expected_checkin_show_up_in_the_calendar()
    {
        $sim = Sim::factory()->create(['expiration_date' => '2027-03-01']);

        $this->assertDatabaseHas('calendar_events', [
            'source_type' => Sim::class,
            'source_id' => $sim->id,
            'event_type' => 'sim.expiration',
        ]);

        $sim->update(['expiration_date' => null]);
        $this->assertEquals(0, CalendarEvent::where('source_type', Sim::class)->where('source_id', $sim->id)->where('event_type', 'sim.expiration')->count());
    }

    public function test_checkout_and_checkin_email_the_user_when_enabled()
    {
        Mail::fake();
        $this->settings->set(['sim_checkout_email' => true]);

        $user = User::factory()->create(['email' => 'sim-owner@example.com']);
        $sim = Sim::factory()->create();
        $admin = User::factory()->checkoutSims()->checkinSims()->create();

        $this->actingAsForApi($admin)
            ->postJson(route('api.sims.checkout', $sim), ['checkout_to_type' => 'user', 'assigned_user' => $user->id])
            ->assertStatusMessageIs('success');

        Mail::assertSent(CheckoutSimMail::class, fn ($mail) => $mail->hasTo('sim-owner@example.com'));

        $this->actingAsForApi($admin)
            ->postJson(route('api.sims.checkin', $sim))
            ->assertStatusMessageIs('success');

        Mail::assertSent(CheckinSimMail::class);
    }

    public function test_no_email_when_disabled()
    {
        Mail::fake();

        $this->actingAsForApi(User::factory()->checkoutSims()->create())
            ->postJson(route('api.sims.checkout', Sim::factory()->create()), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create(['email' => 'x@example.com'])->id,
            ])
            ->assertStatusMessageIs('success');

        Mail::assertNotSent(CheckoutSimMail::class);
    }

    public function test_my_assets_page_lists_the_users_sims()
    {
        $user = User::factory()->create();
        $sim = Sim::factory()->checkedOutToUser($user)->create(['msisdn' => '+375291234567']);

        $this->actingAs($user)
            ->get(route('view-assets'))
            ->assertOk()
            ->assertSee('+375 29 123-45-67');

        $this->assertNotNull($sim);
    }

    public function test_edit_form_has_no_checkout_note_field()
    {
        $this->actingAs(User::factory()->editSims()->checkoutSims()->create())
            ->get(route('sims.edit', Sim::factory()->create()))
            ->assertOk()
            ->assertDontSee('name="note"', false);
    }
}
