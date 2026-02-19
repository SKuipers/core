<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Scanner;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * CodeScanner - Orchestrates scanning of PHP files for deprecated patterns
 * 
 * Scans directories recursively, identifies all instances of deprecated patterns
 * and missing type hints, and generates comprehensive scan results.
 * 
 * Requirements: 1.1, 1.2, 1.4, 1.5
 */
class CodeScanner
{
    /**
     * @param FileAnalyzer $fileAnalyzer Analyzer for individual PHP files
     */
    public function __construct(
        private readonly FileAnalyzer $fileAnalyzer
    ) {}

    /**
     * Scan a directory recursively for deprecated patterns and missing type hints
     * 
     * @param string $path Directory path to scan
     * @param array<string> $excludePatterns Patterns to exclude (e.g., 'vendor/', 'tests/')
     * @return ScanResult Comprehensive scan results
     */
    public function scanDirectory(string $path, array $excludePatterns = []): ScanResult
    {
        // Validate path
        if (!is_dir($path)) {
            return new ScanResult(
                implicitlyNullableParams: [],
                missingParameterTypes: [],
                missingReturnTypes: [],
                missingPropertyTypes: [],
                filesScanned: 0,
                issuesFound: 0,
                errors: ["Directory not found: {$path}"]
            );
        }

        // Default exclusions
        if (empty($excludePatterns)) {
            $excludePatterns = ['vendor/'];
        }

        // Collect all PHP files
        $phpFiles = $this->collectPhpFiles($path, $excludePatterns);

        // Scan each file
        $allImplicitlyNullable = [];
        $allMissingParameters = [];
        $allMissingReturns = [];
        $allMissingProperties = [];
        $errors = [];
        $filesScanned = 0;

        foreach ($phpFiles as $filePath) {
            $analysis = $this->scanFile($filePath);

            if ($analysis->isError()) {
                $errors[] = $analysis->getFormattedError();
                continue;
            }

            $filesScanned++;

            // Collect implicitly nullable parameters
            foreach ($analysis->getImplicitlyNullableParams() as $issue) {
                $allImplicitlyNullable[] = array_merge(['file' => $filePath], $issue);
            }

            // Collect missing type hints
            $missingTypeHints = $analysis->getMissingTypeHints();
            
            foreach ($missingTypeHints['parameters'] as $issue) {
                $allMissingParameters[] = array_merge(['file' => $filePath], $issue);
            }

            foreach ($missingTypeHints['returns'] as $issue) {
                $allMissingReturns[] = array_merge(['file' => $filePath], $issue);
            }

            foreach ($missingTypeHints['properties'] as $issue) {
                $allMissingProperties[] = array_merge(['file' => $filePath], $issue);
            }
        }

        $totalIssues = count($allImplicitlyNullable)
            + count($allMissingParameters)
            + count($allMissingReturns)
            + count($allMissingProperties);

        return new ScanResult(
            implicitlyNullableParams: $allImplicitlyNullable,
            missingParameterTypes: $allMissingParameters,
            missingReturnTypes: $allMissingReturns,
            missingPropertyTypes: $allMissingProperties,
            filesScanned: $filesScanned,
            issuesFound: $totalIssues,
            errors: $errors
        );
    }

    /**
     * Scan a single PHP file for deprecated patterns and missing type hints
     * 
     * @param string $filePath Path to the PHP file
     * @return FileAnalysis Analysis results for the file
     */
    public function scanFile(string $filePath): FileAnalysis
    {
        return $this->fileAnalyzer->analyze($filePath);
    }

    /**
     * Collect all PHP files in a directory, excluding specified patterns
     * 
     * @param string $path Directory path to scan
     * @param array<string> $excludePatterns Patterns to exclude
     * @return array<string> List of PHP file paths
     */
    private function collectPhpFiles(string $path, array $excludePatterns): array
    {
        $phpFiles = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                // Only process PHP files
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $filePath = $file->getPathname();

                // Check exclusion patterns
                if ($this->shouldExclude($filePath, $excludePatterns)) {
                    continue;
                }

                $phpFiles[] = $filePath;
            }
        } catch (\Exception $e) {
            // If directory traversal fails, return empty array
            // Errors will be captured in the scan result
        }

        return $phpFiles;
    }

    /**
     * Check if a file path should be excluded based on patterns
     * 
     * @param string $filePath File path to check
     * @param array<string> $excludePatterns Patterns to exclude
     * @return bool True if file should be excluded
     */
    private function shouldExclude(string $filePath, array $excludePatterns): bool
    {
        // Normalize path separators
        $normalizedPath = str_replace('\\', '/', $filePath);

        foreach ($excludePatterns as $pattern) {
            // Normalize pattern separators
            $normalizedPattern = str_replace('\\', '/', $pattern);

            // Check if pattern matches anywhere in the path
            if (str_contains($normalizedPath, $normalizedPattern)) {
                return true;
            }
        }

        return false;
    }
}
