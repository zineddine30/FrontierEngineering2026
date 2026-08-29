<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('benchmark:run-all')]
#[Description('Hit all 10 Query Doctor benchmark endpoints automatically and report status/timing')]
class BenchmarkRunAll extends Command
{
    // Label => path. Uses real seeded IDs — adjust if your data has different IDs.
    protected array $endpoints = [
        'orders'                   => '/api/benchmark/orders',
        'orders/1'                 => '/api/benchmark/orders/1',
        'orders-enterprise'        => '/api/benchmark/orders-enterprise',
        'products'                 => '/api/benchmark/products',
        'products/by-category/1'   => '/api/benchmark/products/by-category/1',
        'products/3/reviews'       => '/api/benchmark/products/3/reviews',
        'products/null-category'   => '/api/benchmark/products/null-category',
        'categories/1/products'    => '/api/benchmark/categories/1/products',
        'users/25/orders'          => '/api/benchmark/users/25/orders',
        'reviews/recent'           => '/api/benchmark/reviews/recent',
    ];

    public function handle(): int
    {
        // Reads APP_URL from .env — make sure it matches your running server (e.g. http://127.0.0.1:8000)
        $baseUrl = rtrim(config('app.url', 'http://127.0.0.1:8000'), '/');

        foreach ($this->endpoints as $label => $path) {
            $start = microtime(true);
            $response = Http::timeout(30)->get($baseUrl.$path);
            $duration = round((microtime(true) - $start) * 1000, 2);

            $status = $response->successful() ? "<info>OK ({$response->status()})</info>" : "<error>FAILED ({$response->status()})</error>";
            $this->line(str_pad($label, 30).$status." — {$duration}ms");
        }

        $this->newLine();
        $this->info('All 10 endpoints hit. Visit /benchmark-results to see the logged query counts.');

        return self::SUCCESS;
    }
}
