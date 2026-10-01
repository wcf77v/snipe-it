<?php

namespace Tests\Feature\Sims;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Sim;
use App\Models\SimPlan;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SimFormAndImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->settings->set([
            'sim_default_country' => 'BY',
            'sim_required_fields' => 'msisdn,supplier_id',
        ]);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('sims.csv', $content);
    }

    public function test_phone_and_operator_are_required_by_default_and_iccid_is_optional()
    {
        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), ['iccid' => '8937501234567890123'])
            ->assertStatusMessageIs('error')
            ->assertMessagesContains(['msisdn', 'supplier_id']);

        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), [
                'msisdn' => '8 029 123-45-67',
                'supplier_id' => Supplier::factory()->create()->id,
            ])
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('sims', ['msisdn' => '+375291234567', 'iccid' => null]);
    }

    public function test_operator_requirement_is_satisfied_by_the_plan()
    {
        $plan = SimPlan::factory()->create();

        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), ['msisdn' => '+375291234567', 'sim_plan_id' => $plan->id])
            ->assertStatusMessageIs('success');

        $this->assertDatabaseHas('sims', ['msisdn' => '+375291234567', 'supplier_id' => $plan->supplier_id]);
    }

    public function test_required_fields_are_configurable()
    {
        $this->settings->set(['sim_required_fields' => 'iccid']);

        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), ['msisdn' => '+375291234567'])
            ->assertStatusMessageIs('error')
            ->assertMessagesContains('iccid');
    }

    public function test_settings_page_saves_required_fields()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('sims.settings.update'), [
                'sim_required_fields' => ['msisdn', 'department_id'],
                'sim_profile_writeback' => 'off',
                'sim_default_country' => 'BY',
            ])
            ->assertRedirect(route('sims.settings.edit'));

        $this->assertEquals(['msisdn', 'department_id'], Sim::requiredFields());
    }

    public function test_monthly_cost_comes_from_the_plan()
    {
        $plan = SimPlan::factory()->create(['monthly_cost' => 9.90]);
        $sim = Sim::factory()->create(['sim_plan_id' => $plan->id]);

        $this->assertEquals('9.90', $sim->monthly_cost);

        $this->actingAsForApi(User::factory()->viewSims()->create())
            ->getJson(route('api.sims.show', $sim))
            ->assertOk()
            ->assertJsonPath('monthly_cost', '9.90');
    }

    public function test_choosing_a_user_in_the_form_checks_the_sim_out_with_department()
    {
        $department = Department::factory()->create();
        $owner = User::factory()->create(['department_id' => $department->id]);
        $sim = Sim::factory()->create();

        $this->actingAs(User::factory()->editSims()->checkoutSims()->checkinSims()->create())
            ->put(route('sims.update', $sim), [
                'msisdn' => $sim->msisdn,
                'supplier_id' => $sim->supplier_id,
                'assigned_user' => $owner->id,
                'redirect_option' => 'item',
            ])
            ->assertSessionHasNoErrors();

        $sim->refresh();
        $this->assertTrue($sim->checkedOutToUser());
        $this->assertEquals($owner->id, $sim->assigned_to);
        $this->assertEquals($department->id, $sim->department_id);
        $this->assertDatabaseHas('action_logs', ['item_type' => Sim::class, 'item_id' => $sim->id, 'action_type' => 'checkout']);
    }

    public function test_clearing_the_user_checks_the_sim_in()
    {
        $sim = Sim::factory()->checkedOutToUser()->create();

        $this->actingAs(User::factory()->editSims()->checkoutSims()->checkinSims()->create())
            ->put(route('sims.update', $sim), [
                'msisdn' => $sim->msisdn,
                'supplier_id' => $sim->supplier_id,
                'assigned_user' => '',
                'redirect_option' => 'item',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($sim->refresh()->assigned_to);
    }

    public function test_empty_user_field_keeps_a_sim_installed_in_an_asset()
    {
        $asset = Asset::factory()->create();
        $sim = Sim::factory()->checkedOutToAsset($asset)->create();

        $this->actingAs(User::factory()->editSims()->checkoutSims()->checkinSims()->create())
            ->put(route('sims.update', $sim), [
                'msisdn' => $sim->msisdn,
                'supplier_id' => $sim->supplier_id,
                'assigned_user' => '',
                'redirect_option' => 'item',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($sim->refresh()->checkedOutToAsset());
    }

    public function test_assigning_a_user_requires_checkout_permission()
    {
        $sim = Sim::factory()->create();

        $this->actingAs(User::factory()->editSims()->create())
            ->put(route('sims.update', $sim), [
                'msisdn' => $sim->msisdn,
                'supplier_id' => $sim->supplier_id,
                'assigned_user' => User::factory()->create()->id,
            ])
            ->assertSessionHasErrors('assigned_user');

        $this->assertNull($sim->refresh()->assigned_to);
    }

    public function test_create_page_renders_with_sections()
    {
        $this->actingAs(User::factory()->createSims()->checkoutSims()->create())
            ->get(route('sims.create'))
            ->assertOk()
            ->assertSee(trans('admin/sims/general.section_main'))
            ->assertSee(trans('admin/sims/general.section_owner'))
            ->assertSee('name="assigned_user"', false)
            ->assertDontSee('name="monthly_cost"', false);
    }

    public function test_import_page_renders_and_requires_permission()
    {
        $this->actingAs(User::factory()->createSims()->create())
            ->get(route('sims.import.create'))
            ->assertOk();

        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('sims.import.create'))
            ->assertForbidden();
    }

    public function test_import_template_downloads()
    {
        $response = $this->actingAs(User::factory()->createSims()->create())
            ->get(route('sims.import.template'))
            ->assertOk();

        $this->assertStringContainsString('msisdn;phone_country;iccid', $response->streamedContent());
    }

    public function test_import_check_mode_saves_nothing()
    {
        $supplier = Supplier::factory()->create(['name' => 'A1']);

        $this->actingAs(User::factory()->createSims()->editSims()->create())
            ->post(route('sims.import.store'), [
                'file' => $this->csv("msisdn;operator\n+375 29 111-11-11;A1\n"),
                'mode' => 'check',
            ])
            ->assertOk()
            ->assertViewHas('result', fn ($result) => $result['created'] === 1 && $result['dry_run'] === true);

        $this->assertDatabaseMissing('sims', ['msisdn' => '+375291111111']);
        $this->assertNotNull($supplier);
    }

    public function test_import_creates_updates_and_reports_errors()
    {
        Supplier::factory()->create(['name' => 'A1']);
        $plan = SimPlan::factory()->create(['name' => 'Business 10GB']);
        $department = Department::factory()->create(['name' => 'IT']);
        $owner = User::factory()->create(['username' => 'ivanov']);
        $existing = Sim::factory()->create(['msisdn' => '+375292222222', 'name' => 'old name']);

        // Mix of translated titles (from the SIM module's ru-RU strings) and internal keys.
        $csv = "Номер телефона;Оператор связи;Тариф;user;department;Название\n"
            ."8 029 111-11-11;A1;Business 10GB;ivanov;IT;New SIM\n"
            ."+375 29 222-22-22;A1;;;;Renamed\n"
            ."12345;A1;;;;Bad number\n"
            .";NoSuchOperator;;;;Missing phone\n";

        $this->actingAs(User::factory()->createSims()->editSims()->checkoutSims()->checkinSims()->create())
            ->post(route('sims.import.store'), ['file' => $this->csv($csv), 'mode' => 'import'])
            ->assertOk()
            ->assertViewHas('result', fn ($result) => $result['created'] === 1 && $result['updated'] === 1 && $result['failed'] === 2);

        $created = Sim::where('msisdn', '+375291111111')->firstOrFail();
        $this->assertEquals($plan->id, $created->sim_plan_id);
        $this->assertEquals($department->id, $created->department_id);
        $this->assertEquals($owner->id, $created->assigned_to);
        $this->assertTrue($created->checkedOutToUser());

        $this->assertEquals('Renamed', $existing->refresh()->name);
    }

    public function test_import_accepts_the_list_export_user_format()
    {
        Supplier::factory()->create(['name' => 'A1']);
        $owner = User::factory()->create(['username' => 'petrov']);

        $this->actingAs(User::factory()->createSims()->editSims()->checkoutSims()->create())
            ->post(route('sims.import.store'), [
                'file' => $this->csv("msisdn,operator,user\n+375293333333,A1,\"Петров Пётр (petrov)\"\n"),
                'mode' => 'import',
            ])
            ->assertOk();

        $this->assertEquals($owner->id, Sim::where('msisdn', '+375293333333')->value('assigned_to'));
    }

    public function test_import_requires_permission()
    {
        $this->actingAs(User::factory()->viewSims()->create())
            ->post(route('sims.import.store'), ['file' => $this->csv("msisdn\n+375291111111\n"), 'mode' => 'import'])
            ->assertForbidden();
    }
}
