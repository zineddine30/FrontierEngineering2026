<?php

namespace App\Console\Commands;

use App\Ai\Agents\Tools\CommandRunnerTool;
use App\Ai\Agents\Tools\FileReaderTool;
use App\Ai\Agents\Tools\PatchApplierTool;
use App\Support\Agent\AgentLogger;
use App\Support\Agent\DeepSeekClient;
use App\Support\Agent\GeminiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('agent:run-query-doctor {--reset} {--provider=gemini : gemini or deepseek}')]
#[Description('Run the Query Doctor agent loop, then interactively apply and re-verify fixes')]
class RunQueryDoctor extends Command
{
    protected const MAX_STEPS = 15;
    protected const CONTROLLER_PATH = 'app/Http/Controllers/BenchmarkController.php';
    protected const BASELINE_PATH = 'app/Http/Controllers/BenchmarkController.baseline.php';

    protected string $systemPrompt = <<<PROMPT
    You are Query Doctor, an agent auditing a Laravel project for N+1 query
    problems and missing indexes.

    Target file: app/Http/Controllers/BenchmarkController.php

    Steps to follow:
    1. Call read_file on the target Controller to see all 10 benchmark methods.
    2. For each method with a real N+1 issue, call propose_fix with the exact
       method name, the exact original code snippet, and the exact fixed snippet.
       Do this for EVERY finding — do not only describe it in text.
    3. Once all fixes are proposed, call run_command with "php artisan test"
       to confirm the current baseline still runs without errors.
    4. Produce a short final text summary. You never apply fixes yourself —
       a human reviews and applies each proposed fix separately.
    PROMPT;

    public function handle(
        GeminiClient $gemini,
        DeepSeekClient $deepseek,
        FileReaderTool $fileReader,
        CommandRunnerTool $commandRunner,
        PatchApplierTool $patchApplier,
        AgentLogger $logger
    ): int {
        if ($this->option('reset')) {
            $this->resetToBaseline();
        }

        if (! $this->controllerHasKnownIssues()) {
            $this->warn('BenchmarkController.php appears to already be optimized.');
            if (! $this->confirm('Run the agent anyway?', false)) {
                $this->info('Tip: run with --reset to restore the naive baseline first.');
                return self::SUCCESS;
            }
        }

        $logger->startNewRun();
        $provider = $this->option('provider');

        $proposals = $provider === 'deepseek'
            ? $this->runWithDeepSeek($deepseek, $fileReader, $commandRunner, $logger)
            : $this->runWithGemini($gemini, $deepseek, $fileReader, $commandRunner, $logger);

        return $this->handleProposals($proposals, $patchApplier, $commandRunner, $logger);
    }

    // ---- Gemini loop, with AUTOMATIC fallback to DeepSeek on quota exhaustion ----
    protected function runWithGemini($gemini, $deepseek, $fileReader, $commandRunner, $logger): array
    {
        $contents = [['role' => 'user', 'parts' => [['text' => 'Please begin the audit.']]]];
        $proposals = [];

        for ($step = 1; $step <= self::MAX_STEPS; $step++) {
            $this->info("Step {$step}: calling Gemini...");
            $response = $gemini->send($this->systemPrompt, $contents);
            $logger->logModelTurn($step, $response);

            // NEW: detect quota/API errors explicitly instead of silently treating as "no findings"
            if (isset($response['error'])) {
                $status = $response['error']['status'] ?? 'UNKNOWN';
                $this->error("Gemini error ({$status}): ".($response['error']['message'] ?? 'unknown'));

                if ($status === 'RESOURCE_EXHAUSTED') {
                    $this->warn('Gemini daily quota exhausted — switching to DeepSeek for the rest of this run.');
                    return $this->runWithDeepSeek($deepseek, $fileReader, $commandRunner, $logger, $proposals);
                }

                $this->error('Aborting run due to unrecoverable API error.');
                return $proposals;
            }

            $modelParts = $response['candidates'][0]['content']['parts'] ?? [];
            $functionCall = $gemini->extractFunctionCall($response);

            if (! $functionCall) {
                $finalText = $gemini->extractText($response);
                $this->info('Agent finished diagnosing.');
                $this->line($finalText);
                $logger->logFinalAnswer($finalText);
                break;
            }

            $contents[] = ['role' => 'model', 'parts' => $modelParts];

            if ($functionCall['name'] === 'propose_fix') {
                $proposals[] = $functionCall['args'];
                $toolResult = ['status' => 'queued for human review'];
            } else {
                $toolResult = match ($functionCall['name']) {
                    'read_file'   => $fileReader->run($functionCall['args']['path'] ?? ''),
                    'run_command' => $commandRunner->run($functionCall['args']['command'] ?? ''),
                    default       => ['error' => 'Unknown tool: '.$functionCall['name']],
                };
            }

            $logger->logToolCall($step, $functionCall['name'], $functionCall['args'] ?? [], $toolResult);
            $contents[] = ['role' => 'user', 'parts' => [[
                'functionResponse' => ['name' => $functionCall['name'], 'response' => $toolResult],
            ]]];
        }

        return $proposals;
    }

    // ---- DeepSeek loop (OpenAI-compatible format) ----
    protected function runWithDeepSeek($deepseek, $fileReader, $commandRunner, $logger, array $proposals = []): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt],
            ['role' => 'user', 'content' => 'Please begin the audit.'],
        ];

        for ($step = 1; $step <= self::MAX_STEPS; $step++) {
            $this->info("Step {$step}: calling DeepSeek...");
            $response = $deepseek->send($messages);
            $logger->logModelTurn($step, $response);

            if ($deepseek->isError($response)) {
                $this->error('DeepSeek error: '.json_encode($response['error']));
                return $proposals;
            }

            $toolCalls = $deepseek->extractToolCalls($response);

            if (empty($toolCalls)) {
                $finalText = $deepseek->extractText($response);
                $this->info('Agent finished diagnosing.');
                $this->line($finalText);
                $logger->logFinalAnswer($finalText);
                break;
            }

            $messages[] = $response['choices'][0]['message'];

            foreach ($toolCalls as $call) {
                $name = $call['function']['name'];
                $args = json_decode($call['function']['arguments'], true);

                if ($name === 'propose_fix') {
                    $proposals[] = $args;
                    $toolResult = ['status' => 'queued for human review'];
                } else {
                    $toolResult = match ($name) {
                        'read_file'   => $fileReader->run($args['path'] ?? ''),
                        'run_command' => $commandRunner->run($args['command'] ?? ''),
                        default       => ['error' => 'Unknown tool: '.$name],
                    };
                }

                $logger->logToolCall($step, $name, $args, $toolResult);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'],
                    'content' => json_encode($toolResult),
                ];
            }
        }

        return $proposals;
    }

    // ---- Shared: human approval + apply + auto re-verify (unchanged from before) ----
    protected function handleProposals(array $proposals, $patchApplier, $commandRunner, $logger): int
    {
        if (empty($proposals)) {
            $this->warn('No fixes were proposed.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(count($proposals).' fix(es) proposed. Review each before applying:');
        $applied = [];

        foreach ($proposals as $fix) {
            $this->newLine();
            $this->line("<fg=yellow>Method:</> {$fix['method']}");
            $this->line("<fg=yellow>Reason:</> {$fix['reason']}");
            $this->line("<fg=red>- {$fix['old_code']}</>");
            $this->line("<fg=green>+ {$fix['new_code']}</>");

            if ($this->confirm('Apply this fix to BenchmarkController.php?', true)) {
                $result = $patchApplier->run(self::CONTROLLER_PATH, $fix['old_code'], $fix['new_code']);
                $logger->logToolCall(0, 'propose_fix_applied', $fix, $result);

                if (isset($result['error'])) {
                    $this->error("Failed: {$result['error']}");
                } else {
                    $this->info("Applied. Backup saved as {$result['backup']}");
                    $applied[] = $fix['method'];
                }
            } else {
                $this->line('Skipped.');
            }
        }

        if (empty($applied)) {
            $this->warn('No fixes were applied — skipping re-verification.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Re-running the full benchmark to verify the improvement...');
        $before = $this->sumQueryCounts(storage_path('logs/benchmark-before'));
        $commandRunner->run('php artisan test');
        // \Illuminate\Support\Facades\Artisan::call('benchmark:run-all'); 
        $this->call('benchmark:run-all');
        $after = $this->sumQueryCounts(storage_path('logs/benchmark'));

        $this->newLine();
        $this->table(['Metric', 'Before', 'After', 'Change'], [[
            'Total queries (10 endpoints)', $before, $after,
            $before > 0 ? round((($before - $after) / $before) * 100, 1).'% reduction' : 'n/a',
        ]]);

        $logger->logFinalAnswer("Applied: ".implode(', ', $applied).". Verified: {$before} -> {$after}.");
        return self::SUCCESS;
    }

    protected function resetToBaseline(): void
    {
        $baseline = base_path(self::BASELINE_PATH);
        $target = base_path(self::CONTROLLER_PATH);

        if (! file_exists($baseline)) {
            $this->error('Baseline file missing: '.self::BASELINE_PATH);
            return;
        }

        copy($baseline, $target);
        $this->info('Controller reset to naive baseline.');

        collect(glob(storage_path('logs/benchmark').'/*.json'))->each(fn ($f) => unlink($f));
        $this->info('Cleared storage/logs/benchmark/.');
    }

    protected function controllerHasKnownIssues(): bool
    {
        $content = file_get_contents(base_path(self::CONTROLLER_PATH));
        return str_contains($content, 'Order::all()') || str_contains($content, 'Product::all()');
    }

    /* protected function sumQueryCounts(string $folder): int
    {
        if (! is_dir($folder)) return 0;
        return collect(glob($folder.'/*.json'))->sum(function ($file) {
            $entries = json_decode(file_get_contents($file), true);
            $last = end($entries);
            return $last['query_count'] ?? 0;
        });
    } */
   protected function sumQueryCounts(string $folder): int
    {
        if (! is_dir($folder)) {
            $this->warn("Folder not found: {$folder}");
            return 0;
        }

        $files = glob($folder.'/*.json');

        if (empty($files)) {
            $this->warn("No log files found in: {$folder}");
            return 0;
        }

        return collect($files)->sum(function ($file) {
            $entries = json_decode(file_get_contents($file), true);
            $last = end($entries);
            return $last['query_count'] ?? 0;
        });
    }
}
