<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Tests;

use LPhenom\LPhenom\Build\DevPackageFilter;
use LPhenom\LPhenom\Build\PharFileFilter;
use LPhenom\LPhenom\Build\BuildAnnotationScanner;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DevPackageFilter and PharFileFilter.
 */
final class BuildFilterTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = dirname(__DIR__);
    }

    // --- DevPackageFilter tests ---

    public function testDevPackageFilterIdentifiesDevPackages(): void
    {
        $filter = DevPackageFilter::createFromComposer($this->basePath);

        $devPaths = $filter->getDevPaths();
        $prodPaths = $filter->getProdPaths();

        self::assertGreaterThan(0, count($devPaths), 'Should detect dev packages');
        self::assertGreaterThan(0, count($prodPaths), 'Should detect prod packages');
    }

    public function testDevPackageFilterDetectsPhpunitAsDev(): void
    {
        $filter = DevPackageFilter::createFromComposer($this->basePath);

        $phpunitFile = $this->basePath . '/vendor/phpunit/phpunit/src/Framework/TestCase.php';
        if (is_file($phpunitFile)) {
            self::assertTrue($filter->isDevFile($phpunitFile));
        }
    }

    public function testDevPackageFilterDetectsLphenomAsProd(): void
    {
        $filter = DevPackageFilter::createFromComposer($this->basePath);

        $coreFile = $this->basePath . '/vendor/lphenom/core/src/Config/Config.php';
        if (is_file($coreFile)) {
            self::assertFalse($filter->isDevFile($coreFile));
            self::assertTrue($filter->isProdFile($coreFile));
        }
    }

    public function testDevPackageFilterSrcFilesAreNotDev(): void
    {
        $filter = DevPackageFilter::createFromComposer($this->basePath);

        $srcFile = $this->basePath . '/src/Application.php';
        self::assertFalse($filter->isDevFile($srcFile));
    }

    // --- PharFileFilter tests ---

    public function testPharFilterExcludesDevPackages(): void
    {
        $filter = PharFileFilter::createDefault($this->basePath);

        $phpunitFile = $this->basePath . '/vendor/phpunit/phpunit/src/Framework/TestCase.php';
        if (is_file($phpunitFile)) {
            self::assertFalse($filter->shouldInclude($phpunitFile));
        }
    }

    public function testPharFilterIncludesLphenomProdFiles(): void
    {
        $filter = PharFileFilter::createDefault($this->basePath);

        // No annotation → builds everywhere → included in PHAR
        $configFile = $this->basePath . '/vendor/lphenom/core/src/Config/Config.php';
        if (is_file($configFile)) {
            self::assertTrue($filter->shouldInclude($configFile));
        }
    }

    public function testPharFilterExcludesNoneAnnotation(): void
    {
        $filter = PharFileFilter::createDefault($this->basePath);

        // Cli tools have @lphenom-build none
        $cliFile = $this->basePath . '/vendor/lphenom/redis/src/Cli/Screen/KeyListScreen.php';
        if (is_file($cliFile)) {
            self::assertFalse($filter->shouldInclude($cliFile));
        }
    }

    public function testPharFilterIncludesSharedAnnotation(): void
    {
        $filter = PharFileFilter::createDefault($this->basePath);

        // shared-only files → included in PHAR
        $pdoFile = $this->basePath . '/vendor/lphenom/db/src/Driver/PdoMySqlConnection.php';
        if (is_file($pdoFile)) {
            self::assertTrue($filter->shouldInclude($pdoFile));
        }
    }

    public function testPharFilterIncludesSharedKphpAnnotation(): void
    {
        $filter = PharFileFilter::createDefault($this->basePath);

        // shared,kphp files → included in PHAR
        $respFile = $this->basePath . '/vendor/lphenom/redis/src/Resp/RespClient.php';
        if (is_file($respFile)) {
            self::assertTrue($filter->shouldInclude($respFile));
        }
    }

    public function testPharFilterIncludesNonPhpFiles(): void
    {
        $filter = PharFileFilter::createDefault($this->basePath);

        // Non-PHP files always included (configs, etc.)
        self::assertTrue($filter->shouldInclude('/some/path/config.json'));
        self::assertTrue($filter->shouldInclude('/some/path/readme.md'));
    }

    public function testPharFilterIncludesSrcFiles(): void
    {
        $filter = PharFileFilter::createDefault($this->basePath);

        // src/ files (no annotation) → builds everywhere → included
        $appFile = $this->basePath . '/src/Application.php';
        self::assertTrue($filter->shouldInclude($appFile));
    }
}

