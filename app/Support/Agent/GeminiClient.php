<?php

namespace App\Support\Agent;

use Illuminate\Support\Facades\Http;

class GeminiClient
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model  = config('services.gemini.model', 'gemini-3.6-flash'); 
    }

    // Sends the full conversation history + tool declarations, returns the raw JSON response
    public function send(string $systemPrompt, array $contents): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $body = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'           => $contents,
            'tools'              => [['function_declarations' => $this->toolDeclarations()]],
        ];

        return Http::timeout(60)->post($url, $body)->json();
    }

    // JSON Schema the model uses to know how/when to call each tool
    protected function toolDeclarations(): array
    {
        return [
            [
                'name' => 'read_file',
                'description' => 'Read the content of a whitelisted project file (Controllers or Models only).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Relative path, e.g. app/Http/Controllers/BenchmarkController.php',
                        ],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'run_command',
                'description' => 'Run one whitelisted shell command and return its output.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'command' => [
                            'type' => 'string',
                            'description' => 'Exact whitelisted command, e.g. "php artisan test"',
                        ],
                    ],
                    'required' => ['command'],
                ],
            ],
            [
                'name' => 'propose_fix',
                'description' => 'Register one concrete N+1 fix for later human review and application. Call this once per finding instead of only describing it in text.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'method' => ['type' => 'string', 'description' => 'The method name being fixed, e.g. orders'],
                        'old_code' => ['type' => 'string', 'description' => 'The EXACT original line(s) to replace, copied verbatim from the file you read'],
                        'new_code' => ['type' => 'string', 'description' => 'The exact replacement line(s) with eager loading applied'],
                        'reason' => ['type' => 'string', 'description' => 'One sentence explaining the N+1 pattern found'],
                    ],
                    'required' => ['method', 'old_code', 'new_code', 'reason'],
                ],
            ],
        ];
    }

    // Extracts the first functionCall block, if the model requested a tool
    public function extractFunctionCall(array $response): ?array
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                return $part['functionCall'];
            }
        }

        return null;
    }

    // Extracts the plain text answer once the model has no more tool calls to make
    public function extractText(array $response): string
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];

        return collect($parts)->pluck('text')->filter()->implode("\n");
    }
}
