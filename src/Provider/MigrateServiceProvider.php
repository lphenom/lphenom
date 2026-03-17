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

/**
 * Registers the migration system from lphenom/migrate.
 *
 * Receives pre-loaded MigrationInterface[] instances from the factory
 * (AppFactory auto-discovers them via MigrationLoader; KphpAppFactory
 * expects them to be passed explicitly).
 *
 * @lphenom-build shared,kphp
 */
final class MigrateServiceProvider implements ServiceProviderInterface
{
    /** @var MigrationInterface[] */
    private array $userMigrations;

    /**
     * @param MigrationInterface[] $userMigrations  All migrations to register
     */
    public function __construct(array $userMigrations = [])
    {
        $this->userMigrations = $userMigrations;
    }

    public function register(Container $container, Config $config): void
    {
        $container->set(MigrationRegistry::class, new MigrationRegistryFactory(
            $this->userMigrations
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

    /**
     * @param MigrationInterface[] $userMigrations
     */
    public function __construct(array $userMigrations)
    {
        $this->userMigrations = $userMigrations;
    }

    public function create(Container $container): mixed
    {
        $registry = new MigrationRegistry();

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
