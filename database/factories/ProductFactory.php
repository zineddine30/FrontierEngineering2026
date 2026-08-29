<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label'             => $this->faker->numerify('prod-####'),
            'description'       => $this->faker->text(100),
            'price'             => $this->faker->randomFloat(2, 0, 100),
            'image'             => $this->faker->imageUrl(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
    }
}
