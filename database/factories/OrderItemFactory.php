<?php

namespace Database\Factories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quantity'          => $this->faker->randomFloat(2, 0, 100),
            'price'             => $this->faker->randomFloat(2, 0, 100),
            'total'             => $this->faker->randomFloat(2, 0, 100),
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
    }
}
