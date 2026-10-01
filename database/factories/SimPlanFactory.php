<?php

namespace Database\Factories;

use App\Models\SimPlan;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SimPlanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SimPlan::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'supplier_id' => Supplier::factory(),
            'monthly_cost' => $this->faker->randomFloat(2, 1, 50),
            'created_by' => User::factory()->superuser(),
        ];
    }
}
