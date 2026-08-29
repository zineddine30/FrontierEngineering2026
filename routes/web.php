<?php

use App\Ai\Agents\QueryBuilderAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Ai\Enums\Lab;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/benchmark-results', [App\Http\Controllers\BenchmarkResultsController::class, 'index'])->name('benchmark-results');

/* Route::get('/coach', function (Request $request) {
    $response = (new QueryBuilderAgent)->prompt(
        'Show me all users who signed up last month',
        provider: [
                Lab::Gemini->value => 'gemini-3.6-flash',
                Lab::DeepSeek->value => 'deepseek-v4-pro',
            ],
    );

    $response['query'];       // generated SQL
    $response['explanation']; // plain-language explanation

    return [$response];
}); */


