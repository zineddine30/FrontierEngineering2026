<?php

namespace App\Ai\Agents\Tools;

use Symfony\Component\Process\Process;

class CommandRunnerTool
{
        // Exact-match whitelist only — this is the main safety boundary of the whole agent.
    protected array $whitelist = [
        'php artisan test',
        'php artisan test --filter=BenchmarkBaselineTest',
    ];

    public function run(string $command): array
    {
        $command = trim($command);

        if (! in_array($command, $this->whitelist, true)) {
            return ['error' => "Command not whitelisted: {$command}"];
        }

        $process = Process::fromShellCommandline($command, base_path());
        $process->setTimeout(60);
        $process->run();

        return [
            'command'      => $command,
            'exit_code'    => $process->getExitCode(),
            'output'       => $process->getOutput(),
            'error_output' => $process->getErrorOutput(),
        ];
    }
}
