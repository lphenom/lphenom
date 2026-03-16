<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Console;

use LPhenom\LPhenom\Application;

/**
 * CLI command contract.
 *
 * Each command implements execute() and returns an exit code.
 *
 * KPHP-compatible: no callable, no reflection.
 */
interface CommandInterface
{
    /**
     * Get the command name (e.g. 'migrate', 'queue:work').
     */
    public function getName(): string;

    /**
     * Get a short description for help output.
     */
    public function getDescription(): string;

    /**
     * Execute the command.
     *
     * @param Application $app
     * @param string[]    $args CLI arguments (after command name)
     * @return int exit code (0 = success)
     */
    public function execute(Application $app, array $args): int;
}
