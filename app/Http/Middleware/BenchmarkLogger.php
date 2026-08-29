<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Support\QueryLogger;
use Illuminate\Support\Str;

class BenchmarkLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        QueryLogger::reset();
        $start = microtime(true);

        $response = $next($request);

        $summary = QueryLogger::summary();
        $summary['duration_ms'] = round((microtime(true) - $start) * 1000, 2);
        $summary['endpoint']    = $request->path();
        $summary['timestamp']   = now()->toIso8601String();

        $file = storage_path('logs/benchmark/' . Str::slug($request->path()) . '.json');
        @mkdir(dirname($file), 0755, true);
        $history = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        $history[] = $summary;
        file_put_contents($file, json_encode($history, JSON_PRETTY_PRINT));

        return $response;
    }
}
