<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number'            => $this->faker->numerify('no-####'), 
            'date'              => $this->faker->dateTimeBetween('-10 months', 'now'), 
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
    }
}
