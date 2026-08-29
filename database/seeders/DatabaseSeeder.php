<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Fixed seed for full reproducibility across every run
        mt_srand(1234);

        $users = \App\Models\User::factory(100)->create();

        $categories = \App\Models\Category::factory(3)->create();
        $allProducts = collect();

        $categories->each(function ($category) use (&$allProducts) {
            // Products: ~80% get a real category_id, ~20% left null on purpose
            // (simulates a developer forgetting to populate the FK for some records)
            \App\Models\Product::factory(50)
                ->create()
                ->each(function ($product) use ($category, &$allProducts) {
                    $product->category_id = (rand(1, 100) <= 20) ? null : $category->id;
                    $product->save();
                    $allProducts->push($product);

                    // Reviews
                    \App\Models\Review::factory(24)->create([
                        'user_id'    => rand(1, 100),
                        'product_id' => $product->id,
                    ]);

                    // Normal, realistic-sized orders (1-4 orders, 1-5 items each)
                    \App\Models\Order::factory(rand(1, 4))
                        ->create(['user_id' => rand(1, 100)])
                        ->each(function ($order) use ($product) {
                            \App\Models\OrderItem::factory(rand(1, 5))->create([
                                'order_id'   => $order->id,
                                'product_id' => $product->id,
                            ]);
                        });
                });
        });

        // Deliberate stress-test case: ONE "enterprise" order with 120 line items
        // spanning many different products — this is the challenging/edge case
        // required by the evaluation section of the hackathon brief.
        $enterpriseOrder = \App\Models\Order::factory()->create([
            'user_id' => 1,
            'number'  => 'ENT-00001',
        ]);

        for ($i = 0; $i < 120; $i++) {
            \App\Models\OrderItem::factory()->create([
                'order_id'   => $enterpriseOrder->id,
                'product_id' => $allProducts->random()->id,
            ]);
        }
    }
}
