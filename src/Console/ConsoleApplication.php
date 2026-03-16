<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Console;

use LPhenom\LPhenom\Application;

/**
 * Console application — dispatches commands by name.
 *
 * KPHP-compatible: stores commands in array<string, CommandInterface>,
 * no callable, no reflection, explicit dispatch via if/else on name.
 */
final class ConsoleApplication
{
    /** @var array<string, CommandInterface> */
    private array $commands;

    /** @var Application */
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app      = $app;
        $this->commands = [];
    }

    /**
     * Register a command.
     */
    public function add(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    /**
     * Run the console application with given argv.
     *
     * @param string[] $argv
     * @return int exit code
     */
    public function run(array $argv): int
    {
        // argv[0] = script name, argv[1] = command name
        $commandName = '';
        if (isset($argv[1])) {
            $commandName = $argv[1];
        }

        if ($commandName === '' || $commandName === 'help' || $commandName === '--help') {
            return $this->showHelp();
        }

        $command = $this->commands[$commandName] ?? null;
        if ($command === null) {
            echo 'Unknown command: ' . $commandName . PHP_EOL;
            echo 'Run with --help to see available commands.' . PHP_EOL;
            return 1;
        }

        /** @var string[] $args */
        $args = [];
        $i = 2;
        while ($i < count($argv)) {
            $args[] = $argv[$i];
            $i++;
        }

        return $command->execute($this->app, $args);
    }

    private function showHelp(): int
    {
        echo 'LPhenom Console' . PHP_EOL;
        echo '===============' . PHP_EOL;
        echo '' . PHP_EOL;
        echo 'Available commands:' . PHP_EOL;

        foreach ($this->commands as $name => $cmd) {
            echo '  ' . $name . str_repeat(' ', 24 - strlen($name)) . $cmd->getDescription() . PHP_EOL;
        }

        echo '' . PHP_EOL;
        return 0;
    }
}
