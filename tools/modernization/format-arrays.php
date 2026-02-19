#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Format Arrays Script
 * 
 * This script formats single-line arrays as multi-line following PSR-12 specification.
 * Different thresholds apply based on array type:
 * - Associative arrays: 3 or more elements
 * - Regular arrays: 6 or more elements
 * 
 * Run this after PHP-CS-Fixer.
 * 
 * Usage:
 *   php format-arrays.php <file-or-directory>
 *   php format-arrays.php <file-or-directory> --dry-run
 */

$dryRun = in_array('--dry-run', $argv);

// Find the path argument (skip script name and --dry-run flag)
$path = null;
for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] !== '--dry-run') {
        $path = $argv[$i];
        break;
    }
}

if ($path === null) {
    echo "Usage: php format-arrays.php <file-or-directory> [--dry-run]\n";
    exit(1);
}

if (is_file($path)) {
    formatFile($path, $dryRun);
} elseif (is_dir($path)) {
    formatDirectory($path, $dryRun);
} else {
    echo "Error: Path not found: {$path}\n";
    exit(1);
}

function formatDirectory(string $dir, bool $dryRun): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $count = 0;
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            if (formatFile($file->getPathname(), $dryRun)) {
                $count++;
            }
        }
    }

    echo ($dryRun ? "Would format" : "Formatted") . " {$count} files\n";
}

function formatFile(string $filePath, bool $dryRun): bool
{
    $content = file_get_contents($filePath);
    if ($content === false) {
        return false;
    }

    $originalContent = $content;
    $content = formatArrays($content);

    if ($content !== $originalContent) {
        if (!$dryRun) {
            file_put_contents($filePath, $content);
        }
        echo ($dryRun ? "Would format: " : "Formatted: ") . "{$filePath}\n";
        return true;
    }

    return false;
}

function formatArrays(string $content): string
{
    $lines = explode("\n", $content);
    $result = [];
    
    foreach ($lines as $lineNum => $line) {
        // Check if this line contains a single-line array assignment
        if (preg_match('/^(\s*)(.+?)\s*=\s*\[(.+)\];?\s*$/', $line, $matches)) {
            $indent = $matches[1];
            $prefix = $matches[2];
            $arrayContent = $matches[3];
            
            // Skip if already multi-line or contains nested arrays
            if (str_contains($arrayContent, "\n")) {
                $result[] = $line;
                continue;
            }
            
            // Count elements and check if associative
            $elementCount = countArrayElements($arrayContent);
            $isAssociative = isAssociativeArray($arrayContent);
            
            // Apply different thresholds based on array type
            $threshold = $isAssociative ? 3 : 6;
            
            // Only format if element count exceeds threshold
            if ($elementCount < $threshold) {
                $result[] = $line;
                continue;
            }
            
            // Format as multi-line
            $formatted = formatArrayMultiline($prefix, $arrayContent, $indent);
            $result = array_merge($result, $formatted);
        } else {
            $result[] = $line;
        }
    }
    
    return implode("\n", $result);
}

function countArrayElements(string $arrayContent): int
{
    $depth = 0;
    $count = 0;
    $inString = false;
    $stringChar = null;
    
    for ($i = 0; $i < strlen($arrayContent); $i++) {
        $char = $arrayContent[$i];
        $prevChar = $i > 0 ? $arrayContent[$i - 1] : '';
        
        // Handle string boundaries
        if (($char === '"' || $char === "'") && $prevChar !== '\\') {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
                $stringChar = null;
            }
            continue;
        }
        
        if ($inString) {
            continue;
        }
        
        // Track array/parenthesis depth
        if ($char === '[' || $char === '(') {
            $depth++;
        } elseif ($char === ']' || $char === ')') {
            $depth--;
        } elseif ($char === ',' && $depth === 0) {
            $count++;
        }
    }
    
    // Add 1 for the last element (no trailing comma)
    return $count + 1;
}

function formatArrayMultiline(string $prefix, string $arrayContent, string $indent): array
{
    $elementIndent = $indent . '    ';
    $elements = splitArrayElements($arrayContent);
    
    $result = [];
    $result[] = $indent . $prefix . ' = [';
    
    foreach ($elements as $element) {
        $element = trim($element);
        if (!empty($element)) {
            $result[] = $elementIndent . $element . ',';
        }
    }
    
    $result[] = $indent . '];';
    
    return $result;
}

function isAssociativeArray(string $arrayContent): bool
{
    $depth = 0;
    $inString = false;
    $stringChar = null;
    
    for ($i = 0; $i < strlen($arrayContent); $i++) {
        $char = $arrayContent[$i];
        $prevChar = $i > 0 ? $arrayContent[$i - 1] : '';
        
        // Handle string boundaries
        if (($char === '"' || $char === "'") && $prevChar !== '\\') {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
                $stringChar = null;
            }
            continue;
        }
        
        if ($inString) {
            continue;
        }
        
        // Track array/parenthesis depth
        if ($char === '[' || $char === '(') {
            $depth++;
        } elseif ($char === ']' || $char === ')') {
            $depth--;
        } elseif ($char === '=' && $depth === 0) {
            // Check if this is a '=>' (associative array key)
            if ($i + 1 < strlen($arrayContent) && $arrayContent[$i + 1] === '>') {
                return true;
            }
        }
    }
    
    return false;
}

function splitArrayElements(string $arrayContent): array
{
    $elements = [];
    $current = '';
    $depth = 0;
    $inString = false;
    $stringChar = null;
    
    for ($i = 0; $i < strlen($arrayContent); $i++) {
        $char = $arrayContent[$i];
        $prevChar = $i > 0 ? $arrayContent[$i - 1] : '';
        
        // Handle string boundaries
        if (($char === '"' || $char === "'") && $prevChar !== '\\') {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
                $stringChar = null;
            }
            $current .= $char;
            continue;
        }
        
        if ($inString) {
            $current .= $char;
            continue;
        }
        
        // Track depth for nested arrays
        if ($char === '[' || $char === '(') {
            $depth++;
            $current .= $char;
        } elseif ($char === ']' || $char === ')') {
            $depth--;
            $current .= $char;
        } elseif ($char === ',' && $depth === 0) {
            // Found element separator at top level
            $elements[] = $current;
            $current = '';
        } else {
            $current .= $char;
        }
    }
    
    // Add the last element
    if (!empty(trim($current))) {
        $elements[] = $current;
    }
    
    return $elements;
}
