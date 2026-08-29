<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rating'            => $this->faker->randomFloat(2, 0, 5),
            'comment'           => $this->faker->text(100),
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
    }
}
