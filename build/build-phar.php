<?php

/**
 * PHAR build script for lphenom/lphenom.
 *
 * Packages src/ + vendor/ + bin/ + config/ into a self-contained PHAR archive.
 * Run with phar.readonly=0:
 *   php -d phar.readonly=0 build/build-phar.php
 */

declare(strict_types=1);

$buildDir = dirname(__DIR__);
$pharFile = $buildDir . '/build/lphenom.phar';

if (file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile, 0, 'lphenom.phar');
$phar->startBuffering();

/**
 * Add directory contents to the PHAR.
 *
 * @param Phar   $phar
 * @param string $base     absolute path to the source directory
 * @param string $prefix   path prefix inside the PHAR
 */
function addDirectory(Phar $phar, string $base, string $prefix): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }
        $localPath = $prefix . '/' . ltrim(str_replace($base, '', $file->getPathname()), '/');
        $phar->addFile($file->getPathname(), $localPath);
    }
}

// Add source files
addDirectory($phar, $buildDir . '/src', 'src');

// Add config examples
if (is_dir($buildDir . '/config')) {
    addDirectory($phar, $buildDir . '/config', 'config');
}

// Add vendor autoloader (production deps only)
if (is_dir($buildDir . '/vendor')) {
    addDirectory($phar, $buildDir . '/vendor', 'vendor');
}

// Add bin/lphenom entrypoint
if (is_file($buildDir . '/bin/lphenom')) {
    $phar->addFile($buildDir . '/bin/lphenom', 'bin/lphenom');
}

// Bootstrap stub
$stub = <<<'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('lphenom.phar');
require 'phar://lphenom.phar/vendor/autoload.php';

// If run from CLI, dispatch console
if (PHP_SAPI === 'cli') {
    $argv[0] = __FILE__;
    require 'phar://lphenom.phar/bin/lphenom';
} else {
    // If included as library, just provide autoloading
}
__HALT_COMPILER();
STUB;

$phar->setStub($stub);
$phar->stopBuffering();

// Compress
$phar->compressFiles(Phar::GZ);

$size  = number_format((int) filesize($pharFile));
$count = count($phar);

echo "PHAR built: {$pharFile}" . PHP_EOL;
echo "  Size:  {$size} bytes" . PHP_EOL;
echo "  Files: {$count}" . PHP_EOL;
echo "=== PHAR build: OK ===" . PHP_EOL;

