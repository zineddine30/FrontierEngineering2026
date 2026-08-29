<?php

namespace App\Ai\Agents\Tools;

class PatchApplierTool
{
    protected array $allowedPrefixes = ['app/Http/Controllers/'];

    public function run(string $relativePath, string $oldCode, string $newCode): array
    {
        $relativePath = ltrim($relativePath, '/');

        $isAllowed = collect($this->allowedPrefixes)
            ->contains(fn ($prefix) => str_starts_with($relativePath, $prefix));

        if (! $isAllowed) {
            return ['error' => "Patching '{$relativePath}' is not permitted."];
        }

        $fullPath = base_path($relativePath);

        if (! file_exists($fullPath)) {
            return ['error' => "File not found: {$relativePath}"];
        }

        $content = file_get_contents($fullPath);
        $occurrences = substr_count($content, $oldCode);

        if ($occurrences === 0) {
            return ['error' => 'old_code not found in file — refusing to guess.'];
        }

        if ($occurrences > 1) {
            return ['error' => "old_code matches {$occurrences} times — must be unique."];
        }

        $backupPath = $fullPath.'.bak-'.now()->format('Ymd_His');
        copy($fullPath, $backupPath);

        $updated = str_replace($oldCode, $newCode, $content);
        file_put_contents($fullPath, $updated);

        // NEW: validate PHP syntax immediately after writing
        $lintOutput = shell_exec('php -l '.escapeshellarg($fullPath).' 2>&1');

        if (! str_contains($lintOutput, 'No syntax errors detected')) {
            // NEW: automatic rollback if the patch broke the file
            copy($backupPath, $fullPath);

            return [
                'error' => 'Patch produced invalid PHP syntax — automatically rolled back.',
                'lint_output' => trim($lintOutput),
            ];
        }

        return [
            'path' => $relativePath,
            'backup' => basename($backupPath),
            'status' => 'patched',
        ];
    }
}
