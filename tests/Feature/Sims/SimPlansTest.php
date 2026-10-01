<?php

namespace Tests\Feature\Sims;

use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\Sim;
use App\Models\SimPlan;
use App\Models\User;
use Tests\TestCase;

class SimPlansTest extends TestCase
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

    private function fieldsetWith(string $fieldName, bool $required = false): array
    {
        $field = CustomField::factory()->create(['name' => $fieldName, 'format' => 'ANY']);
        $fieldset = CustomFieldset::factory()->create();
        $fieldset->fields()->attach($field, ['order' => 1, 'required' => $required]);

        return [$fieldset, $field];
    }

    public function test_index_page_renders()
    {
        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('simplans.index'))
            ->assertOk();
    }

    public function test_index_requires_permission()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('simplans.index'))
            ->assertForbidden();
    }

    public function test_show_page_renders()
    {
        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('simplans.show', SimPlan::factory()->create()))
            ->assertOk();
    }

    public function test_create_page_renders_and_requires_permission()
    {
        $this->actingAs(User::factory()->createSims()->create())
            ->get(route('simplans.create'))
            ->assertOk();

        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('simplans.create'))
            ->assertForbidden();
    }

    public function test_can_create_plan()
    {
        [$fieldset] = $this->fieldsetWith('APN');

        $this->actingAs(User::factory()->createSims()->create())
            ->post(route('simplans.store'), ['name' => 'M2M 1GB', 'fieldset_id' => $fieldset->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sim_plans', ['name' => 'M2M 1GB', 'fieldset_id' => $fieldset->id]);
    }

    public function test_api_lists_plans()
    {
        $plan = SimPlan::factory()->create();

        $this->actingAsForApi(User::factory()->viewSims()->create())
            ->getJson(route('api.simplans.index'))
            ->assertOk()
            ->assertResponseContainsInRows($plan);
    }

    public function test_sim_uses_fieldset_of_its_plan()
    {
        [$m2mFieldset, $apnField] = $this->fieldsetWith('APN');
        [$corpFieldset, $costCenterField] = $this->fieldsetWith('Cost center');

        $m2m = SimPlan::factory()->create(['fieldset_id' => $m2mFieldset->id]);
        $corp = SimPlan::factory()->create(['fieldset_id' => $corpFieldset->id]);

        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), [
                'iccid' => '8970101234567890123',
                'sim_plan_id' => $m2m->id,
                $apnField->db_column => 'internet.m2m',
                $costCenterField->db_column => 'should be ignored',
            ])
            ->assertStatusMessageIs('success');

        $sim = Sim::where('iccid', '8970101234567890123')->firstOrFail();
        $this->assertEquals($m2m->id, $sim->sim_plan_id);
        $this->assertEquals('internet.m2m', $sim->customFieldValue($apnField->fresh()));
        $this->assertNull($sim->customFieldValue($costCenterField->fresh()));

        $this->assertEquals([$apnField->id], $sim->customFields()->pluck('id')->all());
        $this->assertNotEquals($corp->fieldset_id, $sim->customFieldset()->id);
    }

    public function test_required_plan_fields_are_enforced()
    {
        [$fieldset] = $this->fieldsetWith('APN', true);
        $plan = SimPlan::factory()->create(['fieldset_id' => $fieldset->id]);

        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), [
                'iccid' => '8970101234567890123',
                'sim_plan_id' => $plan->id,
            ])
            ->assertStatusMessageIs('error');

        $this->assertDatabaseMissing('sims', ['iccid' => '8970101234567890123']);
    }

    public function test_plan_fills_operator_and_cost_when_empty()
    {
        $plan = SimPlan::factory()->create(['monthly_cost' => 12.50]);

        $this->actingAsForApi(User::factory()->createSims()->create())
            ->postJson(route('api.sims.store'), [
                'iccid' => '8970101234567890123',
                'sim_plan_id' => $plan->id,
            ])
            ->assertStatusMessageIs('success');

        $sim = Sim::where('iccid', '8970101234567890123')->firstOrFail();
        $this->assertEquals($plan->supplier_id, $sim->supplier_id);
        $this->assertEquals('12.50', $sim->monthly_cost);
    }

    public function test_custom_fields_endpoint_returns_plan_fields()
    {
        [$fieldset, $field] = $this->fieldsetWith('APN');
        $plan = SimPlan::factory()->create(['fieldset_id' => $fieldset->id]);

        $this->actingAs(User::factory()->createSims()->create())
            ->get(route('sims.custom-fields', ['sim_plan_id' => $plan->id]))
            ->assertOk()
            ->assertSee($field->db_column);
    }

    public function test_plan_of_another_company_cannot_be_assigned()
    {
        [$companyA, $companyB] = \App\Models\Company::factory()->count(2)->create();
        $foreignPlan = SimPlan::factory()->create(['company_id' => $companyB->id]);
        $user = $companyA->users()->save(User::factory()->createSims()->make());

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($user)
            ->postJson(route('api.sims.store'), [
                'iccid' => '8970101234567890123',
                'sim_plan_id' => $foreignPlan->id,
            ])
            ->assertStatusMessageIs('error')
            ->assertMessagesContains('sim_plan_id');
    }

    public function test_edit_page_renders_and_requires_permission()
    {
        $plan = SimPlan::factory()->create();

        $this->actingAs(User::factory()->editSims()->create())
            ->get(route('simplans.edit', $plan))
            ->assertOk();

        $this->actingAs(User::factory()->viewSims()->create())
            ->get(route('simplans.edit', $plan))
            ->assertForbidden();
    }

    public function test_plan_with_sims_cannot_be_deleted()
    {
        $plan = SimPlan::factory()->create();
        Sim::factory()->create(['sim_plan_id' => $plan->id]);

        $this->actingAs(User::factory()->deleteSims()->viewSims()->create())
            ->delete(route('simplans.destroy', $plan))
            ->assertRedirect(route('simplans.show', $plan));

        $this->assertNotSoftDeleted($plan);
    }
}
