<?php

namespace App\Support\Agent;

use Illuminate\Support\Facades\Http;

class DeepSeekClient
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.deepseek.key');
        $this->model  = config('services.deepseek.model', 'deepseek-v4-flash');
    }

    // OpenAI-compatible chat completions format
    public function send(array $messages): array
    {
        return Http::withToken($this->apiKey)
            ->timeout(60)
            ->post('https://api.deepseek.com/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'tools' => $this->toolDeclarations(),
            ])
            ->json();
    }

    protected function toolDeclarations(): array
    {
        $fn = fn ($name, $desc, $props, $required) => [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $desc,
                'parameters' => ['type' => 'object', 'properties' => $props, 'required' => $required],
            ],
        ];

        return [
            $fn('read_file', 'Read a whitelisted project file.', ['path' => ['type' => 'string']], ['path']),
            $fn('run_command', 'Run one whitelisted shell command.', ['command' => ['type' => 'string']], ['command']),
            $fn('propose_fix', 'Register one concrete N+1 fix for human review.', [
                'method' => ['type' => 'string'],
                'old_code' => ['type' => 'string'],
                'new_code' => ['type' => 'string'],
                'reason' => ['type' => 'string'],
            ], ['method', 'old_code', 'new_code', 'reason']),
        ];
    }

    public function extractToolCalls(array $response): array
    {
        return $response['choices'][0]['message']['tool_calls'] ?? [];
    }

    public function extractText(array $response): string
    {
        return $response['choices'][0]['message']['content'] ?? '';
    }

    public function isError(array $response): bool
    {
        return isset($response['error']);
    }
}
