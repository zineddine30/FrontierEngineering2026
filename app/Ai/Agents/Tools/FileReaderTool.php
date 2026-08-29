<?php

namespace App\Ai\Agents\Tools;

class FileReaderTool
{
    // Only these directories may be read — keeps the agent focused
    // and prevents it from touching config, .env, or unrelated files.
    protected array $allowedPrefixes = [
        'app/Http/Controllers/',
        'app/Models/',
    ];

    public function run(string $relativePath): array
    {
        $relativePath = ltrim($relativePath, '/');

        $isAllowed = collect($this->allowedPrefixes)
            ->contains(fn ($prefix) => str_starts_with($relativePath, $prefix));

        if (! $isAllowed) {
            return ['error' => "Reading '{$relativePath}' is not permitted."];
        }

        $fullPath = base_path($relativePath);

        // Block path-traversal tricks (e.g. "../../.env") from escaping app/
        if (! str_starts_with(realpath($fullPath) ?: '', base_path('app'))) {
            return ['error' => 'Path resolves outside the allowed app/ directory.'];
        }

        if (! file_exists($fullPath)) {
            return ['error' => "File not found: {$relativePath}"];
        }

        return [
            'path'    => $relativePath,
            'content' => file_get_contents($fullPath),
        ];
    }
}
