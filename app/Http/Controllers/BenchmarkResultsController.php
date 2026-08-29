<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BenchmarkResultsController extends Controller
{
    public function index()
    {
            $rows = collect(glob(storage_path('logs/benchmark/*.json')))
            ->map(function ($file) {
                $entries = json_decode(file_get_contents($file), true); // نخزّنها في متغير أولاً
                return end($entries); // الآن end() تستقبل متغيراً حقيقياً
            });

        return view('benchmark.results', ['rows' => $rows]);
    }
}
