<?php

namespace Tests\Unit\Presenters;

use App\Models\User;
use App\Presenters\SimPresenter;
use Tests\TestCase;

class SimPresenterTest extends TestCase
{
    public function test_iccid_column_is_switchable(): void
    {
        $this->actingAs(User::factory()->viewSims()->create());

        $iccid = $this->columnByField('iccid');

        $this->assertNotNull($iccid);
        $this->assertTrue($iccid['switchable']);
    }

    public function test_layout_includes_status_and_last_checkin(): void
    {
        $this->actingAs(User::factory()->viewSims()->create());

        $fields = array_column($this->layout(), 'field');

        $this->assertContains('status', $fields);
        $this->assertContains('last_checkin', $fields);
    }

    public function test_secret_columns_require_view_secrets(): void
    {
        $this->actingAs(User::factory()->viewSims()->create());

        $fields = array_column($this->layout(), 'field');

        $this->assertNotContains('pin', $fields);
        $this->assertNotContains('puk', $fields);
        $this->assertNotContains('activation_code', $fields);

        $this->actingAs(User::factory()->viewSims()->viewSimSecrets()->create());

        $fields = array_column($this->layout(), 'field');

        $this->assertContains('pin', $fields);
        $this->assertContains('puk', $fields);
        $this->assertContains('activation_code', $fields);
    }

    public function test_msisdn_links_to_sim_not_tel(): void
    {
        $this->actingAs(User::factory()->viewSims()->create());

        $msisdn = $this->columnByField('msisdn_formatted');

        $this->assertNotNull($msisdn);
        $this->assertSame('simsLinkFormatter', $msisdn['formatter']);
        $this->assertNotSame('phoneFormatter', $msisdn['formatter'] ?? null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function layout(): array
    {
        return json_decode(SimPresenter::dataTableLayout(), true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function columnByField(string $field): ?array
    {
        foreach ($this->layout() as $column) {
            if (($column['field'] ?? null) === $field) {
                return $column;
            }
        }

        return null;
    }
}
