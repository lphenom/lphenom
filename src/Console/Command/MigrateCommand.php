<?php

declare(strict_types=1);

namespace LPhenom\Lphenom\Console\Command;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Lphenom\Application;
use LPhenom\Lphenom\Console\CommandInterface;
use LPhenom\Migrate\Migrator;

/**
 * Run pending database migrations.
 */
final class MigrateCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'migrate';
    }

    public function getDescription(): string
    {
        return 'Run all pending database migrations';
    }

    public function execute(Application $app, array $args): int
    {
        $container = $app->getContainer();

        /** @var Migrator $migrator */
        $migrator = $container->get(Migrator::class);
        /** @var ConnectionInterface $conn */
        $conn = $container->get(ConnectionInterface::class);

        $migrator->prepare();

        $pending = $migrator->getPending();
        if (count($pending) === 0) {
            echo 'Nothing to migrate.' . PHP_EOL;
            return 0;
        }

        echo 'Running ' . count($pending) . ' migration(s)...' . PHP_EOL;

        $applied = $migrator->migrate($conn);

        foreach ($applied as $version) {
            echo '  Migrated: ' . $version . PHP_EOL;
        }

        echo 'Done.' . PHP_EOL;
        return 0;
    }
}
