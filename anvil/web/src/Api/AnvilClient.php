<?php

declare(strict_types=1);

namespace Anvil\Web\Api;

/**
 * Thin wrapper around proc_open that shells out to the anvilctl engine.
 *
 * The command is passed as an array (no shell invocation) so arguments are
 * never subject to shell expansion / injection. stdout, stderr and the exit
 * code are captured and returned as an AnvilResult.
 */
final class AnvilClient
{
    public function __construct(private string $anvilctlPath)
    {
    }

    public function run(string ...$args): AnvilResult
    {
        $command = array_merge([$this->anvilctlPath], $args);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes);
        if ($process === false) {
            return new AnvilResult(1, '', 'Failed to launch anvilctl.');
        }

        // We never write to stdin.
        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = (int) proc_close($process);

        return new AnvilResult($exitCode, $stdout, $stderr);
    }
}
