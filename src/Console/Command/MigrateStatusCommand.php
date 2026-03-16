<?php

declare(strict_types=1);

namespace LPhenom\Lphenom\Console\Command;

use LPhenom\Lphenom\Application;
use LPhenom\Lphenom\Console\CommandInterface;
use LPhenom\Migrate\Migrator;

/**
 * Show migration status.
 */
final class MigrateStatusCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'migrate:status';
    }

    public function getDescription(): string
    {
        return 'Show status of all migrations';
    }

    public function execute(Application $app, array $args): int
    {
        $container = $app->getContainer();

        /** @var Migrator $migrator */
        $migrator = $container->get(Migrator::class);

        $migrator->prepare();

        $status = $migrator->status();

        if (count($status) === 0) {
            echo 'No migrations registered.' . PHP_EOL;
            return 0;
        }

        echo 'Migration Status:' . PHP_EOL;
        foreach ($status as $version => $state) {
            $icon = $state === 'applied' ? '[x]' : '[ ]';
            echo '  ' . $icon . ' ' . $version . ' — ' . $state . PHP_EOL;
        }

        return 0;
    }
}
