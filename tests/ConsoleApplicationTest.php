<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Tests;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\LPhenom\Application;
use LPhenom\LPhenom\Console\CommandInterface;
use LPhenom\LPhenom\Console\ConsoleApplication;
use PHPUnit\Framework\TestCase;

final class ConsoleApplicationTest extends TestCase
{
    public function testHelpShowsCommands(): void
    {
        $app     = $this->makeApp();
        $console = new ConsoleApplication($app);

        $console->add(new class () implements CommandInterface {
            public function getName(): string
            {
                return 'test:cmd';
            }

            public function getDescription(): string
            {
                return 'A test command';
            }

            public function execute(Application $app, array $args): int
            {
                return 0;
            }
        });

        ob_start();
        $exitCode = $console->run(['lphenom', '--help']);
        $output   = (string) ob_get_clean();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('test:cmd', $output);
        self::assertStringContainsString('A test command', $output);
    }

    public function testUnknownCommand(): void
    {
        $app     = $this->makeApp();
        $console = new ConsoleApplication($app);

        ob_start();
        $exitCode = $console->run(['lphenom', 'nonexistent']);
        $output   = (string) ob_get_clean();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unknown command: nonexistent', $output);
    }

    public function testCommandExecution(): void
    {
        $app     = $this->makeApp();
        $console = new ConsoleApplication($app);

        $executed = false;

        $console->add(new class ($executed) implements CommandInterface {
            /** @var bool */
            private bool $executed;

            public function __construct(bool &$executed)
            {
                $this->executed = &$executed;
            }

            public function getName(): string
            {
                return 'test:run';
            }

            public function getDescription(): string
            {
                return 'Run test';
            }

            public function execute(Application $app, array $args): int
            {
                $this->executed = true;
                return 42;
            }
        });

        $exitCode = $console->run(['lphenom', 'test:run']);

        self::assertTrue($executed);
        self::assertSame(42, $exitCode);
    }

    private function makeApp(): Application
    {
        return new Application(
            new Container(),
            new Config([]),
            '/tmp'
        );
    }
}
