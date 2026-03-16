<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Build;

/**
 * Resolves PHP file dependencies via use/extends/implements statements.
 *
 * Uses PSR-4 namespace→directory mapping (read from Composer autoload_psr4.php)
 * to resolve FQCN → file path. Performs topological sort for require_once order.
 *
 * KPHP-compatible: no Reflection, no dynamic class loading.
 */
final class DependencyResolver
{
    /** @var string */
    private string $basePath;

    /**
     * Namespace prefix → directory mapping.
     * Longer prefixes are checked first (most specific wins).
     *
     * @var array<string, string>
     */
    private array $namespacePrefixes;

    /**
     * @param string               $basePath
     * @param array<string, string> $namespacePrefixes namespace prefix => absolute directory path
     */
    public function __construct(string $basePath, array $namespacePrefixes)
    {
        $this->basePath = $basePath;
        $this->namespacePrefixes = $namespacePrefixes;

        // Sort by prefix length descending for most-specific-first matching
        uksort($this->namespacePrefixes, static function (string $a, string $b): int {
            return strlen($b) - strlen($a);
        });
    }

    /**
     * Create a resolver using Composer's autoload_psr4.php.
     *
     * Only includes LPhenom\* namespaces (our packages).
     */
    public static function createFromComposer(string $basePath): self
    {
        $psr4File = $basePath . '/vendor/composer/autoload_psr4.php';
        /** @var array<string, string> $prefixes */
        $prefixes = [];

        if (is_file($psr4File)) {
            /** @var array<string, string[]> $map */
            $map = require $psr4File;

            foreach ($map as $ns => $dirs) {
                // Only resolve LPhenom namespaces
                if (strpos($ns, 'LPhenom\\') !== 0) {
                    continue;
                }
                // Use the first directory
                if (count($dirs) > 0) {
                    $prefixes[$ns] = $dirs[0];
                }
            }
        }

        return new self($basePath, $prefixes);
    }

    /**
     * Resolve FQCN to a file path.
     *
     * @return string|null Absolute file path or null if not found
     */
    public function resolveClass(string $fqcn): ?string
    {
        // Remove leading backslash
        if ($fqcn !== '' && $fqcn[0] === '\\') {
            $fqcn = substr($fqcn, 1);
        }

        foreach ($this->namespacePrefixes as $prefix => $dir) {
            if (strpos($fqcn, $prefix) === 0) {
                $relative = substr($fqcn, strlen($prefix));
                $filePath = $dir . '/' . str_replace('\\', '/', $relative) . '.php';
                if (is_file($filePath)) {
                    return realpath($filePath) ?: $filePath;
                }
            }
        }

        return null;
    }

    /**
     * Parse use statements from a PHP file.
     *
     * @return string[] FQCNs referenced by use statements
     */
    public function parseUseStatements(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        /** @var string[] $uses */
        $uses = [];

        // Match: use Foo\Bar\Baz;
        // Match: use Foo\Bar\Baz as Alias;
        $matches = [];
        preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+\w+)?\s*;/m', $content, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $use) {
                // Skip PHP built-in and non-LPhenom classes for dependency resolution
                if (strpos($use, 'LPhenom\\') === 0) {
                    $uses[] = $use;
                }
            }
        }

        // Match: extends ClassName and implements InterfaceName
        // These are usually already captured by use statements, but just in case
        // someone uses fully-qualified names inline
        $matches2 = [];
        preg_match_all('/(?:extends|implements)\s+([\w\\\\]+(?:\s*,\s*[\w\\\\]+)*)/m', $content, $matches2);
        if (isset($matches2[1])) {
            // Parse the namespace context
            $nsMatch = [];
            preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $content, $nsMatch);
            $namespace = $nsMatch[1] ?? '';

            foreach ($matches2[1] as $group) {
                $items = explode(',', $group);
                foreach ($items as $item) {
                    $item = trim($item);
                    // If it's a FQCN
                    if (strpos($item, '\\') !== false && strpos($item, 'LPhenom\\') === 0) {
                        if (!in_array($item, $uses, true)) {
                            $uses[] = $item;
                        }
                    }
                }
            }
        }

        return $uses;
    }

    /**
     * Build a dependency graph for given files.
     *
     * @param string[] $files Absolute paths of files to include
     * @return array<string, string[]> file → list of files it depends on
     */
    public function buildDependencyGraph(array $files): array
    {
        // Build a set of allowed files for quick lookup
        /** @var array<string, bool> $fileSet */
        $fileSet = [];
        foreach ($files as $f) {
            $real = realpath($f);
            $fileSet[$real !== false ? $real : $f] = true;
        }

        /** @var array<string, string[]> $graph */
        $graph = [];

        foreach ($files as $file) {
            $real = realpath($file);
            $key = $real !== false ? $real : $file;

            $uses = $this->parseUseStatements($key);
            /** @var string[] $deps */
            $deps = [];

            foreach ($uses as $fqcn) {
                $depFile = $this->resolveClass($fqcn);
                if ($depFile !== null && isset($fileSet[$depFile]) && $depFile !== $key) {
                    $deps[] = $depFile;
                }
            }

            $graph[$key] = $deps;
        }

        return $graph;
    }

    /**
     * Topological sort — returns files in dependency order (dependencies first).
     *
     * @param string[] $files Absolute paths
     * @return string[] Sorted paths (dependencies before dependents)
     */
    public function topologicalSort(array $files): array
    {
        $graph = $this->buildDependencyGraph($files);

        /** @var string[] $sorted */
        $sorted = [];

        /** @var array<string, int> $state 0=unvisited, 1=visiting, 2=visited */
        $state = [];
        foreach ($graph as $node => $_) {
            $state[$node] = 0;
        }

        // Also add files that might not be keys in the graph
        foreach ($files as $f) {
            $real = realpath($f);
            $key = $real !== false ? $real : $f;
            if (!isset($state[$key])) {
                $state[$key] = 0;
                $graph[$key] = [];
            }
        }

        /**
         * DFS visit function.
         *
         * @param string $node
         * @param array<string, string[]> $graph
         * @param array<string, int> $state
         * @param string[] $sorted
         */
        $visit = static function (string $node) use (&$visit, &$graph, &$state, &$sorted): void {
            if ($state[$node] === 2) {
                return; // already visited
            }
            if ($state[$node] === 1) {
                return; // circular dependency — skip
            }

            $state[$node] = 1; // visiting

            foreach ($graph[$node] as $dep) {
                if (isset($state[$dep])) {
                    $visit($dep);
                }
            }

            $state[$node] = 2; // visited
            $sorted[] = $node;
        };

        foreach (array_keys($state) as $node) {
            if ($state[$node] === 0) {
                $visit($node);
            }
        }

        return $sorted;
    }
}

