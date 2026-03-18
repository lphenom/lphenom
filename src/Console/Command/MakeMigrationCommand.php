<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Console\Command;

use LPhenom\LPhenom\Application;
use LPhenom\LPhenom\Console\CommandInterface;
use LPhenom\Migrate\Command\MakeCommand;

/**
 * Generate a new migration file.
 *
 * Delegates to lphenom/migrate MakeCommand.
 *
 * Usage:
 *   php bin/lphenom make:migration create_users_table
 */
final class MakeMigrationCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:migration';
    }

    public function getDescription(): string
    {
        return 'Create a new migration file';
    }

    public function execute(Application $app, array $args): int
    {
        $name = $args[0] ?? '';

        $migrationsPath = $app->getBasePath() . '/database/migrations';

        $command = new MakeCommand($migrationsPath, $name);

        return $command->run();
    }
}

