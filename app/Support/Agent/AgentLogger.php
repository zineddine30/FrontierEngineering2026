<?php

namespace App\Support\Agent;

class AgentLogger
{
    protected string $runFile;

    // Creates a fresh timestamped JSON file for this run
    public function startNewRun(): void
    {
        $dir = storage_path('logs/agent-runs');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->runFile = $dir.'/run_'.now()->format('Y-m-d_His').'.json';
        file_put_contents($this->runFile, json_encode(['steps' => []], JSON_PRETTY_PRINT));
    }

    public function logModelTurn(int $step, array $rawResponse): void
    {
        $this->append(['type' => 'model_turn', 'step' => $step, 'raw' => $rawResponse]);
    }

    public function logToolCall(int $step, string $tool, array $input, array $output): void
    {
        $this->append([
            'type'      => 'tool_call',
            'step'      => $step,
            'tool'      => $tool,
            'input'     => $input,
            'output'    => $output,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function logFinalAnswer(string $text): void
    {
        $this->append(['type' => 'final_answer', 'text' => $text]);
    }

    public function logStepLimitReached(): void
    {
        $this->append(['type' => 'step_limit_reached']);
    }

    // Appends one entry without overwriting previous ones
    protected function append(array $entry): void
    {
        $data = json_decode(file_get_contents($this->runFile), true);
        $data['steps'][] = $entry;
        file_put_contents($this->runFile, json_encode($data, JSON_PRETTY_PRINT));
    }
}
