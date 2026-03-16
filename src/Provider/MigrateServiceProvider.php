<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;
use LPhenom\LPhenom\ServiceProviderInterface;
use LPhenom\Migrate\MigrationRegistry;
use LPhenom\Migrate\Migrator;
use LPhenom\Migrate\SchemaRepository;
use LPhenom\Queue\Driver\Schema\DbSchema;
use LPhenom\Realtime\Migration\CreateRealtimeEventsTable;

/**
 * Registers the migration system from lphenom/migrate.
 *
 * Automatically collects framework-provided migrations:
 *   - realtime_events table (lphenom/realtime)
 *   - jobs table (lphenom/queue — via raw SQL)
 *
 * User-defined migrations are registered via config/migrations.php
 * which returns MigrationInterface[] array.
 */
final class MigrateServiceProvider implements ServiceProviderInterface
{
    /** @var MigrationInterface[] */
    private array $userMigrations;

    /**
     * @param MigrationInterface[] $userMigrations
     */
    public function __construct(array $userMigrations = [])
    {
        $this->userMigrations = $userMigrations;
    }

    public function register(Container $container, Config $config): void
    {
        $container->set(MigrationRegistry::class, new MigrationRegistryFactory(
            $this->userMigrations,
            $config
        ));

        $container->set(SchemaRepository::class, new SchemaRepositoryFactory());
        $container->set(Migrator::class, new MigratorFactory());
    }

    public function boot(Container $container, Config $config): void
    {
        // no-op
    }
}

/**
 * @internal
 */
final class MigrationRegistryFactory implements ServiceFactoryInterface
{
    /** @var MigrationInterface[] */
    private array $userMigrations;
    /** @var Config */
    private Config $config;

    /**
     * @param MigrationInterface[] $userMigrations
     */
    public function __construct(array $userMigrations, Config $config)
    {
        $this->userMigrations = $userMigrations;
        $this->config         = $config;
    }

    public function create(Container $container): mixed
    {
        $registry = new MigrationRegistry();

        // Register realtime migration
        $realtimeEnabled = $this->config->get('realtime.enabled', true);
        if ($realtimeEnabled === true) {
            $registry->register(new CreateRealtimeEventsTable());
        }

        // Register queue migration (wrapping raw SQL into MigrationInterface)
        $queueDriver = $this->config->get('queue.driver', 'database');
        if ($queueDriver === 'database') {
            $queueTable = $this->config->get('queue.table', 'jobs');
            $tableStr   = is_string($queueTable) ? $queueTable : 'jobs';
            $registry->register(new QueueTableMigration($tableStr));
        }

        // Register user-defined migrations
        foreach ($this->userMigrations as $migration) {
            $registry->register($migration);
        }

        return $registry;
    }
}

/**
 * @internal
 */
final class SchemaRepositoryFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var ConnectionInterface $conn */
        $conn = $container->get(ConnectionInterface::class);
        return new SchemaRepository($conn);
    }
}

/**
 * @internal
 */
final class MigratorFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var MigrationRegistry $registry */
        $registry = $container->get(MigrationRegistry::class);
        /** @var SchemaRepository $repository */
        $repository = $container->get(SchemaRepository::class);
        return new Migrator($registry, $repository);
    }
}

/**
 * Wraps lphenom/queue DbSchema DDL into MigrationInterface.
 *
 * @internal
 */
final class QueueTableMigration implements MigrationInterface
{
    /** @var string */
    private string $table;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function up(ConnectionInterface $conn): void
    {
        $conn->execute(DbSchema::createTable($this->table));
    }

    public function down(ConnectionInterface $conn): void
    {
        $conn->execute(DbSchema::dropTable($this->table));
    }

    public function getVersion(): string
    {
        return '20260313000000';
    }
}
