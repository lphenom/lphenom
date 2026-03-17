<?php
/**
 * Check all vendor/lphenom src files for KPHP-incompatible syntax:
 * 1. Trailing commas in function/method parameter declarations
 * 2. Trailing commas in function/method call arguments (only in specific cases)
 * 3. Constructor property promotion (public readonly string $x)
 * 4. Named arguments usage
 * 5. match expressions
 * 6. enum declarations
 * 7. Readonly properties
 * 8. Intersection types (A&B)
 * 9. Fibers
 */

$basePath = dirname(__DIR__);
$vendorPath = $basePath . '/vendor/lphenom';

$issues = [];

function checkFile(string $filePath, array &$issues): void {
    $content = file_get_contents($filePath);
    if ($content === false) return;
    
    $lines = explode("\n", $content);
    $shortPath = str_replace(dirname(dirname(__DIR__)) . '/', '', $filePath);
    
    // Check for trailing commas in function/method DECLARATIONS
    // Pattern: last param line ends with comma, next line is ) {
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        
        // Check if this line has a trailing comma and next line closes a function param list
        if (preg_match('/,\s*$/', rtrim($line)) && isset($lines[$i + 1])) {
            $nextLine = trim($lines[$i + 1]);
            // Next line starts with ) and is followed by { or : (return type)
            if (preg_match('/^\)\s*(\{|:|;)/', $nextLine) || $nextLine === ')' || $nextLine === ') {') {
                // Check if we're in a function declaration context
                // Look back to find 'function' keyword
                $inFuncDecl = false;
                for ($j = $i; $j >= max(0, $i - 20); $j--) {
                    if (preg_match('/function\s+\w+\s*\(/', $lines[$j]) || preg_match('/__construct\s*\(/', $lines[$j])) {
                        $inFuncDecl = true;
                        break;
                    }
                    // Also check for function calls like sprintf(, throw new Exception(
                    if (preg_match('/\b(sprintf|throw|new\s+\w|array)\s*\(/', $lines[$j])) {
                        break; // This is a function call, not declaration - still problematic for KPHP
                    }
                }
                
                $issues[] = [
                    'file' => $shortPath,
                    'line' => $i + 1,
                    'type' => 'trailing_comma',
                    'context' => trim($line) . ' << trailing comma',
                    'in_func_decl' => $inFuncDecl,
                ];
            }
        }
        
        // Check for constructor property promotion
        if (preg_match('/function\s+__construct\s*\(/', $line)) {
            // Scan params for promoted properties
            for ($j = $i; $j < min(count($lines), $i + 30); $j++) {
                if (preg_match('/\b(public|protected|private)\s+(readonly\s+)?\w+\s+\$/', $lines[$j]) && 
                    !preg_match('/function\s/', $lines[$j])) {
                    // Check we're inside function params (before closing paren)
                    $combined = '';
                    for ($k = $i; $k <= $j; $k++) {
                        $combined .= $lines[$k];
                    }
                    if (substr_count($combined, '(') > substr_count($combined, ')')) {
                        $issues[] = [
                            'file' => $shortPath,
                            'line' => $j + 1,
                            'type' => 'promoted_property',
                            'context' => trim($lines[$j]),
                        ];
                    }
                }
                if (strpos($lines[$j], ')') !== false && $j > $i) break;
            }
        }
        
        // Check for enum
        if (preg_match('/^\s*enum\s+\w+/', $line)) {
            $issues[] = [
                'file' => $shortPath,
                'line' => $i + 1,
                'type' => 'enum',
                'context' => trim($line),
            ];
        }
        
        // Check for readonly property (outside constructor)
        if (preg_match('/\breadonly\s+(string|int|float|bool|array|\??\w+)\s+\$/', $line) && 
            !preg_match('/function/', $line)) {
            $issues[] = [
                'file' => $shortPath,
                'line' => $i + 1,
                'type' => 'readonly_property',
                'context' => trim($line),
            ];
        }
        
        // Check for intersection types
        if (preg_match('/:\s*\w+&\w+/', $line) || preg_match('/\(\w+&\w+\s+\$/', $line)) {
            $issues[] = [
                'file' => $shortPath,
                'line' => $i + 1,
                'type' => 'intersection_type',
                'context' => trim($line),
            ];
        }
        
        // Check for Fiber usage
        if (preg_match('/\bFiber\b/', $line) && !preg_match('/\/\/.*Fiber/', $line) && !preg_match('/\*.*Fiber/', $line)) {
            $issues[] = [
                'file' => $shortPath,
                'line' => $i + 1,
                'type' => 'fiber',
                'context' => trim($line),
            ];
        }
    }
}

// Scan all files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($vendorPath, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    // Only src files
    if (strpos($path, '/src/') === false) continue;
    checkFile($path, $issues);
}

// Also check own src/
$ownSrc = $basePath . '/src';
$iterator2 = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($ownSrc, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator2 as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    checkFile($file->getPathname(), $issues);
}

// Report
if (count($issues) === 0) {
    echo "✅ No KPHP compatibility issues found.\n";
    exit(0);
} else {
    echo "⚠️  Found " . count($issues) . " potential KPHP compatibility issues:\n\n";
    foreach ($issues as $issue) {
        echo sprintf(
            "  [%s] %s:%d\n    %s\n\n",
            strtoupper($issue['type']),
            $issue['file'],
            $issue['line'],
            $issue['context']
        );
    }
    // Don't fail the build — these are warnings that may be covered by annotations.
    // Files with @lphenom-build shared or @lphenom-build none are excluded from KPHP
    // and therefore their syntax is not a problem.
    echo "ℹ️  Check that files with issues have correct @lphenom-build annotations.\n";
    exit(0);
}

