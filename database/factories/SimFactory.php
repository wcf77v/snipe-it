<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Location;
use App\Models\Sim;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SimFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Sim::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => 'SIM '.$this->faker->unique()->numberBetween(1000, 999999),
            // 19-digit ICCID starting with the telecom prefix 89.
            'iccid' => '89'.$this->faker->unique()->numerify('#################'),
            'msisdn' => '+79'.$this->faker->numerify('#########'),
            'phone_country' => 'RU',
            'imsi' => $this->faker->numerify('250##########'),
            'sim_type' => Sim::TYPE_PHYSICAL,
            'supplier_id' => Supplier::factory(),
            'pin' => $this->faker->numerify('####'),
            'puk' => $this->faker->numerify('########'),
            'created_by' => User::factory()->superuser(),
        ];
    }

    public function esim(): static
    {
        return $this->state(fn () => [
            'sim_type' => Sim::TYPE_ESIM,
            'activation_code' => 'LPA:1$smdp.example.com$'.$this->faker->bothify('????-####-????'),
        ]);
    }

    public function checkedOutToUser(?User $user = null): static
    {
        return $this->state(fn () => [
            'assigned_to' => $user->id ?? User::factory(),
            'assigned_type' => User::class,
            'last_checkout' => now()->subDay(),
        ]);
    }

    public function checkedOutToAsset(?Asset $asset = null): static
    {
        return $this->state(fn () => [
            'assigned_to' => $asset->id ?? Asset::factory(),
            'assigned_type' => Asset::class,
            'last_checkout' => now()->subDay(),
        ]);
    }

    public function checkedOutToLocation(?Location $location = null): static
    {
        return $this->state(fn () => [
            'assigned_to' => $location->id ?? Location::factory(),
            'assigned_type' => Location::class,
            'last_checkout' => now()->subDay(),
        ]);
    }
}
