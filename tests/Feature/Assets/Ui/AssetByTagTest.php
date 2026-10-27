<?php

namespace Tests\Feature\Assets\Ui;

use App\Models\Asset;
use App\Models\Sim;
use App\Models\User;
use Tests\TestCase;

class AssetByTagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->settings->set(['sim_required_fields' => '']);
    }

    public function test_exact_asset_tag_opens_the_asset(): void
    {
        $asset = Asset::factory()->create(['asset_tag' => 'TAG-UNIQUE-1']);

        $this->actingAs(User::factory()->viewAssets()->create())
            ->get(route('findbytag/hardware', ['assetTag' => 'TAG-UNIQUE-1']))
            ->assertRedirectToRoute('hardware.show', $asset->id);
    }

    public function test_asset_tag_wins_over_a_matching_sim(): void
    {
        $asset = Asset::factory()->create(['asset_tag' => '2426785']);
        Sim::factory()->create(['msisdn' => '+375292426785']);

        $this->actingAs(User::factory()->viewAssets()->viewSims()->create())
            ->get(route('findbytag/hardware', ['assetTag' => '2426785']))
            ->assertRedirectToRoute('hardware.show', $asset->id);
    }

    public function test_unique_sim_msisdn_fragment_opens_the_sim(): void
    {
        $sim = Sim::factory()->create(['msisdn' => '+375292426785']);

        $this->actingAs(User::factory()->viewAssets()->viewSims()->create())
            ->get(route('findbytag/hardware', ['assetTag' => '2426785']))
            ->assertRedirectToRoute('sims.show', $sim);
    }

    public function test_unique_sim_iccid_opens_the_sim(): void
    {
        $sim = Sim::factory()->create(['iccid' => '8937501234567890123']);

        $this->actingAs(User::factory()->viewAssets()->viewSims()->create())
            ->get(route('findbytag/hardware', ['assetTag' => '8937501234567890123']))
            ->assertRedirectToRoute('sims.show', $sim);
    }

    public function test_without_sim_permission_falls_back_to_asset_index(): void
    {
        Sim::factory()->create(['msisdn' => '+375292426785']);

        $this->actingAs(User::factory()->viewAssets()->create())
            ->get(route('findbytag/hardware', ['assetTag' => '2426785']))
            ->assertRedirectToRoute('hardware.index');
    }

    public function test_not_found_redirects_to_asset_index_with_warning(): void
    {
        $this->actingAs(User::factory()->viewAssets()->viewSims()->create())
            ->get(route('findbytag/hardware', ['assetTag' => 'DOES-NOT-EXIST-XYZ']))
            ->assertRedirectToRoute('hardware.index')
            ->assertSessionHas('warning')
            ->assertSessionHas('search', 'DOES-NOT-EXIST-XYZ');
    }

    public function test_multiple_sim_matches_open_sim_index(): void
    {
        Sim::factory()->create(['msisdn' => '+375291111111']);
        Sim::factory()->create(['msisdn' => '+375292111111']);

        $this->actingAs(User::factory()->viewAssets()->viewSims()->create())
            ->get(route('findbytag/hardware', ['assetTag' => '111111']))
            ->assertRedirectToRoute('sims.index')
            ->assertSessionHas('search', '111111');
    }
}
