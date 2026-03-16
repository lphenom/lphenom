<?php

declare(strict_types=1);

namespace LPhenom\Lphenom\Console\Command;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Lphenom\Application;
use LPhenom\Lphenom\Console\CommandInterface;
use LPhenom\Migrate\Migrator;

/**
 * Rollback the last batch of migrations.
 */
final class MigrateRollbackCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'migrate:rollback';
    }

    public function getDescription(): string
    {
        return 'Rollback the last batch of migrations';
    }

    public function execute(Application $app, array $args): int
    {
        $container = $app->getContainer();

        /** @var Migrator $migrator */
        $migrator = $container->get(Migrator::class);
        /** @var ConnectionInterface $conn */
        $conn = $container->get(ConnectionInterface::class);

        $migrator->prepare();

        $rolledBack = $migrator->rollback($conn);

        if (count($rolledBack) === 0) {
            echo 'Nothing to rollback.' . PHP_EOL;
            return 0;
        }

        foreach ($rolledBack as $version) {
            echo '  Rolled back: ' . $version . PHP_EOL;
        }

        echo 'Done.' . PHP_EOL;
        return 0;
    }
}
