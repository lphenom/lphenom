<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Tests;

use LPhenom\LPhenom\Build\BuildAnnotationScanner;
use PHPUnit\Framework\TestCase;

final class BuildAnnotationScannerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/lphenom_scanner_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testNoAnnotationReturnsAll(): void
    {
        $file = $this->createPhpFile(
            'NoAnnotation.php',
            <<<'PHP'
<?php

declare(strict_types=1);

/**
 * A class without any build annotation.
 */
final class NoAnnotation
{
}
PHP
        );

        $scanner = new BuildAnnotationScanner($this->tmpDir, [$this->tmpDir]);
        $targets = $scanner->parseAnnotation($file);

        self::assertSame(['all'], $targets);
    }

    public function testSharedKphpAnnotation(): void
    {
        $file = $this->createPhpFile(
            'SharedKphp.php',
            <<<'PHP'
<?php

declare(strict_types=1);

/**
 * @lphenom-build shared,kphp
 */
final class SharedKphp
{
}
PHP
        );

        $scanner = new BuildAnnotationScanner($this->tmpDir, [$this->tmpDir]);
        $targets = $scanner->parseAnnotation($file);

        self::assertSame(['shared', 'kphp'], $targets);
    }

    public function testSharedOnlyAnnotation(): void
    {
        $file = $this->createPhpFile(
            'SharedOnly.php',
            <<<'PHP'
<?php

declare(strict_types=1);

/**
 * @lphenom-build shared
 */
final class SharedOnly
{
}
PHP
        );

        $scanner = new BuildAnnotationScanner($this->tmpDir, [$this->tmpDir]);
        $targets = $scanner->parseAnnotation($file);

        self::assertSame(['shared'], $targets);
    }

    public function testKphpOnlyAnnotation(): void
    {
        $file = $this->createPhpFile(
            'KphpOnly.php',
            <<<'PHP'
<?php

declare(strict_types=1);

/**
 * @lphenom-build kphp
 */
final class KphpOnly
{
}
PHP
        );

        $scanner = new BuildAnnotationScanner($this->tmpDir, [$this->tmpDir]);
        $targets = $scanner->parseAnnotation($file);

        self::assertSame(['kphp'], $targets);
    }

    public function testNoneAnnotationReturnsEmpty(): void
    {
        $file = $this->createPhpFile(
            'NoneTarget.php',
            <<<'PHP'
<?php

declare(strict_types=1);

/**
 * @lphenom-build none
 */
final class NoneTarget
{
}
PHP
        );

        $scanner = new BuildAnnotationScanner($this->tmpDir, [$this->tmpDir]);
        $targets = $scanner->parseAnnotation($file);

        self::assertSame([], $targets);
    }

    public function testAnnotationInProseTextIsIgnored(): void
    {
        // This mimics BuildManifestCommand — mentions @lphenom-build in description text
        $file = $this->createPhpFile(
            'ProseText.php',
            <<<'PHP'
<?php

declare(strict_types=1);

/**
 * This class shows @lphenom-build annotations for debugging.
 *
 * It mentions @lphenom-build kphp in a sentence but has no real annotation.
 */
final class ProseText
{
}
PHP
        );

        $scanner = new BuildAnnotationScanner($this->tmpDir, [$this->tmpDir]);
        $targets = $scanner->parseAnnotation($file);

        // Should NOT be detected as kphp-only, because the text is in prose
        self::assertSame(['all'], $targets);
    }

    public function testAnnotationInStringLiteralIsIgnored(): void
    {
        $file = $this->createPhpFile(
            'StringLiteral.php',
            <<<'PHP'
<?php

declare(strict_types=1);

final class StringLiteral
{
    public function getTag(): string
    {
        return '@lphenom-build none';
    }
}
PHP
        );

        $scanner = new BuildAnnotationScanner($this->tmpDir, [$this->tmpDir]);
        $targets = $scanner->parseAnnotation($file);

        // String literal — no docblock annotation — builds everywhere
        self::assertSame(['all'], $targets);
    }

    public function testFileLevelDocblock(): void
    {
        // Some files have @lphenom-build in the file-level docblock before declare
        $file = $this->createPhpFile(
            'FileLevel.php',
            <<<'PHP'
<?php

/**
 * @lphenom-build shared,kphp
 *
 * CacheInterface — KPHP-compatible cache contract.
 */

declare(strict_types=1);

interface FileLevel
{
}
PHP
        );

        $scanner = new BuildAnnotationScanner($this->tmpDir, [$this->tmpDir]);
        $targets = $scanner->parseAnnotation($file);

        self::assertSame(['shared', 'kphp'], $targets);
    }

    public function testScanForTargetKphp(): void
    {
        $this->createPhpFile(
            'A.php',
            <<<'PHP'
<?php
/** @lphenom-build shared,kphp */
final class A {}
PHP
        );
        $this->createPhpFile(
            'B.php',
            <<<'PHP'
<?php
/** @lphenom-build shared */
final class B {}
PHP
        );
        $this->createPhpFile(
            'C.php',
            <<<'PHP'
<?php
/** @lphenom-build none */
final class C {}
PHP
        );
        $this->createPhpFile(
            'D.php',
            <<<'PHP'
<?php
/** No annotation */
final class D {}
PHP
        );

        $scanner = new BuildAnnotationScanner($this->tmpDir, [$this->tmpDir]);
        $kphpFiles = $scanner->scanForTarget('kphp');

        $names = array_map('basename', $kphpFiles);
        sort($names);

        // A (shared,kphp) + D (no annotation = all) → kphp-compatible
        // B (shared only) and C (none) → excluded
        self::assertSame(['A.php', 'D.php'], $names);
    }

    public function testScanForTargetShared(): void
    {
        $this->createPhpFile(
            'A.php',
            <<<'PHP'
<?php
/** @lphenom-build shared,kphp */
final class A {}
PHP
        );
        $this->createPhpFile(
            'B.php',
            <<<'PHP'
<?php
/** @lphenom-build shared */
final class B {}
PHP
        );
        $this->createPhpFile(
            'C.php',
            <<<'PHP'
<?php
/** @lphenom-build none */
final class C {}
PHP
        );
        $this->createPhpFile(
            'D.php',
            <<<'PHP'
<?php
/** No annotation */
final class D {}
PHP
        );

        $scanner = new BuildAnnotationScanner($this->tmpDir, [$this->tmpDir]);
        $sharedFiles = $scanner->scanForTarget('shared');

        $names = array_map('basename', $sharedFiles);
        sort($names);

        // A (shared,kphp), B (shared), D (all) → shared-compatible
        // C (none) → excluded
        self::assertSame(['A.php', 'B.php', 'D.php'], $names);
    }

    public function testMatchesTarget(): void
    {
        $scanner = new BuildAnnotationScanner($this->tmpDir, []);

        // all → matches everything
        self::assertTrue($scanner->matchesTarget(['all'], 'kphp'));
        self::assertTrue($scanner->matchesTarget(['all'], 'shared'));

        // empty (none) → matches nothing
        self::assertFalse($scanner->matchesTarget([], 'kphp'));
        self::assertFalse($scanner->matchesTarget([], 'shared'));

        // specific targets
        self::assertTrue($scanner->matchesTarget(['shared', 'kphp'], 'kphp'));
        self::assertTrue($scanner->matchesTarget(['shared', 'kphp'], 'shared'));
        self::assertFalse($scanner->matchesTarget(['shared'], 'kphp'));
        self::assertTrue($scanner->matchesTarget(['shared'], 'shared'));
        self::assertTrue($scanner->matchesTarget(['kphp'], 'kphp'));
        self::assertFalse($scanner->matchesTarget(['kphp'], 'shared'));
    }

    public function testInvalidTargetValueIgnored(): void
    {
        $file = $this->createPhpFile(
            'Invalid.php',
            <<<'PHP'
<?php

/**
 * @lphenom-build banana
 */
final class Invalid {}
PHP
        );

        $scanner = new BuildAnnotationScanner($this->tmpDir, [$this->tmpDir]);
        $targets = $scanner->parseAnnotation($file);

        // 'banana' is not a valid target — treated as no valid annotation → all
        self::assertSame(['all'], $targets);
    }

    // --- helpers ---

    private function createPhpFile(string $name, string $content): string
    {
        $path = $this->tmpDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
