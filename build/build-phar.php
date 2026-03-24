<?php

/**
 * PHAR build script for lphenom/lphenom.
 *
 * Packages src/ + vendor/ + bin/ + config/ into a self-contained PHAR archive.
 *
 * Features:
 *   - Gzip compression (when ext-zlib is available)
 *   - Eval-obfuscation: PHP files are stripped of comments/whitespace,
 *     compressed via gzdeflate, base64-encoded and wrapped in eval()
 *
 * Filtering:
 *   1. Dev packages (require-dev) are completely excluded from the PHAR.
 *   2. @lphenom-build annotations filter LPhenom source files:
 *      - no annotation / shared,kphp / shared → included
 *      - kphp / none → excluded
 *
 * Environment:
 *   LPHENOM_PHAR_OUTPUT  — custom output path (default: build/lphenom.phar)
 *   LPHENOM_PHAR_OBFUSCATE — set to "0" to disable eval-obfuscation (default: enabled)
 *
 * Run with phar.readonly=0:
 *   php -d phar.readonly=0 build/build-phar.php
 */

declare(strict_types=1);

$buildDir = dirname(__DIR__);

// Autoload to get PharFileFilter
require_once $buildDir . '/vendor/autoload.php';

use LPhenom\LPhenom\Build\PharFileFilter;

$pharFile = getenv('LPHENOM_PHAR_OUTPUT') !== false
    ? (string) getenv('LPHENOM_PHAR_OUTPUT')
    : $buildDir . '/build/lphenom.phar';

if (file_exists($pharFile)) {
    unlink($pharFile);
}

// Initialize filter (dev-package exclusion + annotation filtering)
$filter = PharFileFilter::createDefault($buildDir);

// Obfuscation: enabled by default, disable with LPHENOM_PHAR_OBFUSCATE=0
$obfuscateEnabled = getenv('LPHENOM_PHAR_OBFUSCATE') !== '0';

if ($obfuscateEnabled) {
    echo 'Obfuscation: ENABLED (eval + gzdeflate + base64)' . PHP_EOL;
} else {
    echo 'Obfuscation: DISABLED' . PHP_EOL;
}

// Show what will be excluded
$devPaths = $filter->getDevFilter()->getDevPaths();
echo 'Dev packages excluded (' . count($devPaths) . '):' . PHP_EOL;
foreach ($devPaths as $dp) {
    echo '  ✗ ' . basename(dirname($dp)) . '/' . basename($dp) . PHP_EOL;
}
echo '' . PHP_EOL;

$phar = new Phar($pharFile, 0, 'lphenom.phar');
$phar->startBuffering();

$skippedDev        = 0;
$skippedAnnotation = 0;
$included          = 0;

/**
 * Obfuscate a PHP source file: strip comments/whitespace,
 * compress via gzdeflate, base64-encode and wrap in eval().
 *
 * declare(strict_types=1) is extracted and placed before eval()
 * (PHP syntax requirement: it must be the first statement in a file).
 *
 * @param string $source Original PHP source code
 * @return string Obfuscated PHP code, or original if compression fails
 */
function obfuscatePhp(string $source): string
{
    // Step 1: Strip comments and whitespace via php_strip_whitespace emulation
    $tokens = token_get_all($source);
    $stripped = '';
    foreach ($tokens as $token) {
        if (is_array($token)) {
            [$type, $value] = $token;
            switch ($type) {
                case T_COMMENT:
                case T_DOC_COMMENT:
                    $stripped .= "\n";
                    break;
                case T_WHITESPACE:
                    $stripped .= strpos($value, "\n") !== false ? "\n" : ' ';
                    break;
                case T_OPEN_TAG:
                    $stripped .= '<?php ';
                    break;
                default:
                    $stripped .= $value;
                    break;
            }
        } else {
            $stripped .= $token;
        }
    }

    // Step 2: Compress whitespace — remove empty lines
    $lines = explode("\n", $stripped);
    $lines = array_filter($lines, function (string $line): bool {
        return trim($line) !== '';
    });
    $compressed = implode("\n", $lines);

    // Step 3: Extract declare(strict_types=1) — must be before eval()
    $hasStrictTypes = false;
    $codeBody = $compressed;
    if (preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $codeBody)) {
        $hasStrictTypes = true;
        $codeBody = (string) preg_replace(
            '/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/',
            '',
            $codeBody
        );
    }

    // Remove <?php tag from body (will be in wrapper)
    $codeBody = (string) preg_replace('/<\?php\s*/', '', $codeBody);
    $codeBody = trim($codeBody);

    if ($codeBody === '') {
        return $source;
    }

    // Step 4: gzdeflate + base64 + eval wrapper
    $deflated = gzdeflate($codeBody, 9);
    if ($deflated === false) {
        return $source;
    }

    $encoded = base64_encode($deflated);
    $chunked = chunk_split($encoded, 120, "'\n.'");
    $chunked = rtrim($chunked, "\n.'");
    $chunked = "'" . $chunked . "'";

    $result = "<?php\n";
    if ($hasStrictTypes) {
        $result .= "declare(strict_types=1);\n";
    }
    $result .= "eval(gzinflate(base64_decode({$chunked})));\n";

    return $result;
}

/**
 * Add directory contents to the PHAR with full filtering and optional obfuscation.
 *
 * @param Phar           $phar
 * @param string         $base         Absolute path to the source directory
 * @param string         $prefix       Path prefix inside the PHAR
 * @param PharFileFilter $filter       Annotation + dev-package filter
 * @param int            &$included          Included file counter
 * @param int            &$skippedDev        Skipped-by-dev counter
 * @param int            &$skippedAnnotation Skipped-by-annotation counter
 * @param bool           $applyFilter  Whether to apply filtering
 * @param bool           $obfuscate    Whether to apply eval-obfuscation to PHP files
 */
function addDirectory(
    Phar $phar,
    string $base,
    string $prefix,
    PharFileFilter $filter,
    int &$included,
    int &$skippedDev,
    int &$skippedAnnotation,
    bool $applyFilter = true,
    bool $obfuscate = false
): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }

        $filePath = $file->getPathname();

        if ($applyFilter) {
            // Check dev-package exclusion (fast path — no file reading)
            if ($filter->getDevFilter()->isDevFile($filePath)) {
                $skippedDev++;
                continue;
            }

            // Check @lphenom-build annotation (reads PHP file header)
            if (!$filter->shouldInclude($filePath)) {
                $skippedAnnotation++;
                continue;
            }
        }

        $localPath = $prefix . '/' . ltrim(str_replace($base, '', $filePath), '/');

        // Apply eval-obfuscation to PHP files if enabled
        if ($obfuscate && substr($filePath, -4) === '.php') {
            $source = file_get_contents($filePath);
            if ($source !== false) {
                $phar->addFromString($localPath, obfuscatePhp($source));
            } else {
                $phar->addFile($filePath, $localPath);
            }
        } else {
            $phar->addFile($filePath, $localPath);
        }

        $included++;
    }
}

// Add source files (with annotation filtering + obfuscation)
echo 'Adding src/ ...' . PHP_EOL;
addDirectory($phar, $buildDir . '/src', 'src', $filter, $included, $skippedDev, $skippedAnnotation, true, $obfuscateEnabled);

// Add config (no filtering, no obfuscation)
if (is_dir($buildDir . '/config')) {
    echo 'Adding config/ ...' . PHP_EOL;
    addDirectory($phar, $buildDir . '/config', 'config', $filter, $included, $skippedDev, $skippedAnnotation, false, false);
}

// Add vendor/ (with dev-package + annotation filtering + obfuscation)
if (is_dir($buildDir . '/vendor')) {
    echo 'Adding vendor/ (excluding dev packages + @lphenom-build filtering) ...' . PHP_EOL;
    addDirectory($phar, $buildDir . '/vendor', 'vendor', $filter, $included, $skippedDev, $skippedAnnotation, true, $obfuscateEnabled);

    // Overwrite autoload_files.php with filtered version (exclude dev package entries).
    // Must happen AFTER addDirectory so it overwrites the original.
    $autoloadFilesPath = $buildDir . '/vendor/composer/autoload_files.php';
    if (is_file($autoloadFilesPath)) {
        /** @var array<string, string> $autoloadFiles */
        $autoloadFiles = require $autoloadFilesPath;
        /** @var array<string, string> $filteredEntries */
        $filteredEntries = [];
        $removedAutoloadFiles = 0;

        foreach ($autoloadFiles as $hash => $filePath) {
            if ($filter->getDevFilter()->isDevFile($filePath)) {
                $removedAutoloadFiles++;
                continue;
            }
            $filteredEntries[$hash] = $filePath;
        }

        if ($removedAutoloadFiles > 0) {
            echo '  Filtered autoload_files.php: removed ' . $removedAutoloadFiles . ' dev entries' . PHP_EOL;

            $vendorPrefix = $buildDir . '/vendor';
            $filteredContent  = "<?php\n\n";
            $filteredContent .= "// autoload_files.php @generated by Composer (filtered for PHAR)\n\n";
            $filteredContent .= "\$vendorDir = dirname(__DIR__);\n";
            $filteredContent .= "\$baseDir = dirname(\$vendorDir);\n\n";
            $filteredContent .= "return array(\n";
            foreach ($filteredEntries as $hash => $filePath) {
                $relPath = str_replace($vendorPrefix, '', $filePath);
                $filteredContent .= "    '" . $hash . "' => \$vendorDir . '" . $relPath . "',\n";
            }
            $filteredContent .= ");\n";

            $phar->addFromString('vendor/composer/autoload_files.php', $filteredContent);
        }

        // Also filter autoload_static.php — it has a hardcoded $files array
        // that autoload_real.php uses directly (instead of autoload_files.php).
        $autoloadStaticPath = $buildDir . '/vendor/composer/autoload_static.php';
        if (is_file($autoloadStaticPath) && $removedAutoloadFiles > 0) {
            $staticContent = file_get_contents($autoloadStaticPath);
            if ($staticContent !== false) {
                // Collect hashes of dev file entries to remove
                /** @var string[] $devHashes */
                $devHashes = [];
                foreach ($autoloadFiles as $hash => $filePath) {
                    if ($filter->getDevFilter()->isDevFile($filePath)) {
                        $devHashes[] = $hash;
                    }
                }

                // Remove lines containing dev hashes from the $files array
                $lines = explode("\n", $staticContent);
                /** @var string[] $filteredLines */
                $filteredLines = [];
                foreach ($lines as $line) {
                    $skip = false;
                    foreach ($devHashes as $hash) {
                        if (strpos($line, "'" . $hash . "'") !== false) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip === false) {
                        $filteredLines[] = $line;
                    }
                }

                $phar->addFromString('vendor/composer/autoload_static.php', implode("\n", $filteredLines));
                echo '  Filtered autoload_static.php: removed ' . count($devHashes) . ' dev $files entries' . PHP_EOL;
            }
        }
    }
}

// Add bin/lphenom entrypoint
if (is_file($buildDir . '/bin/lphenom')) {
    $phar->addFile($buildDir . '/bin/lphenom', 'bin/lphenom');
    $included++;
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

// Compress files inside PHAR with gzip (if ext-zlib available)
if (extension_loaded('zlib')) {
    $phar->compressFiles(Phar::GZ);
    echo 'Compression: gzip' . PHP_EOL;
} else {
    echo 'Compression: none (ext-zlib not available)' . PHP_EOL;
}

$size  = number_format((int) filesize($pharFile));
$count = count($phar);

echo PHP_EOL;
echo "PHAR built: {$pharFile}" . PHP_EOL;
echo "  Size:              {$size} bytes" . PHP_EOL;
echo "  Files included:    {$count}" . PHP_EOL;
echo "  Skipped (dev):     {$skippedDev}" . PHP_EOL;
echo "  Skipped (annotation): {$skippedAnnotation}" . PHP_EOL;
echo "=== PHAR build: OK ===" . PHP_EOL;

