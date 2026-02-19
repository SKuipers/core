#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Format a PHP file using PHP-CS-Fixer
 * 
 * Usage: php format-file.php <file-path>
 */

require_once __DIR__ . '/vendor/autoload.php';

if ($argc < 2) {
    echo "Usage: php format-file.php <file-path>\n";
    exit(1);
}

$filePath = $argv[1];

if (!file_exists($filePath)) {
    echo "Error: File not found: {$filePath}\n";
    exit(1);
}

// Run PHP-CS-Fixer
$configPath = __DIR__ . '/.php-cs-fixer.php';
$command = sprintf(
    '%s/vendor/bin/php-cs-fixer fix %s --config=%s --quiet',
    __DIR__,
    escapeshellarg($filePath),
    escapeshellarg($configPath)
);

exec($command, $output, $returnCode);

if ($returnCode === 0) {
    echo "✓ Formatted: {$filePath}\n";
} else {
    echo "✗ Error formatting: {$filePath}\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}
