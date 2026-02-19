#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PHP 8.4 Modernization Tool with Auto-Formatting
 * 
 * This script runs the type hint migration and automatically formats
 * the resulting files with PHP-CS-Fixer to restore proper whitespace.
 * 
 * Usage:
 *   php migrate-with-formatting.php --file=<path>           # Migrate single file
 *   php migrate-with-formatting.php --dir=<path>            # Migrate directory
 *   php migrate-with-formatting.php --dir=<path> --dry-run  # Preview changes
 */

require_once __DIR__ . '/vendor/autoload.php';

use Gibbon\Modernization\Config\MigrationConfig;
use Gibbon\Modernization\Migration\BackupManager;
use Gibbon\Modernization\Migration\CodeMigrator;
use Gibbon\Modernization\Migration\CodeTransformer;
use Gibbon\Modernization\TypeInference\TypeInferenceEngine;
use Gibbon\Modernization\TypeInference\InheritanceAnalyzer;
use Gibbon\Modernization\TypeInference\UsageAnalyzer;
use Gibbon\Modernization\TypeInference\PHPDocAnalyzer;

// Parse command line arguments
$options = getopt('', ['file:', 'dir:', 'dry-run', 'no-format']);
$dryRun = isset($options['dry-run']);
$autoFormat = !isset($options['no-format']);

if (isset($options['file'])) {
    $target = $options['file'];
    $isFile = true;
} elseif (isset($options['dir'])) {
    $target = $options['dir'];
    $isFile = false;
} else {
    echo "Usage:\n";
    echo "  php migrate-with-formatting.php --file=<path>           # Migrate single file\n";
    echo "  php migrate-with-formatting.php --dir=<path>            # Migrate directory\n";
    echo "  php migrate-with-formatting.php --dir=<path> --dry-run  # Preview changes\n";
    echo "  php migrate-with-formatting.php --dir=<path> --no-format # Skip formatting\n";
    exit(1);
}

// Display header
echo "PHP 8.4 Modernization Tool with Auto-Formatting\n";
echo "================================================\n\n";
echo "Target: {$target}\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no files will be modified)" : "LIVE (files will be modified)") . "\n";
echo "Formatting: " . ($autoFormat ? "ENABLED" : "DISABLED") . "\n\n";

// Create configuration
$config = new MigrationConfig(
    rootPath: __DIR__ . '/../..',
    includePaths: [],
    excludePaths: ['vendor', 'node_modules', 'tests', 'cache'],
    dryRun: $dryRun,
    createBackups: !$dryRun,
    backupPath: __DIR__ . '/../../backups',
    confidenceThreshold: 0.7,
    useMixedForLowConfidence: true,
    batchSize: 10
);

// Create components
$phpDocAnalyzer = new PHPDocAnalyzer();
$usageAnalyzer = new UsageAnalyzer();
$inheritanceAnalyzer = new InheritanceAnalyzer();

$typeInference = new TypeInferenceEngine(
    $phpDocAnalyzer,
    $usageAnalyzer,
    $inheritanceAnalyzer
);

$transformer = new CodeTransformer();
$backupManager = new BackupManager($config->backupPath);
$migrator = new CodeMigrator($typeInference, $transformer, $backupManager, $config, $autoFormat);

// Run migration
echo "Starting migration...\n\n";
$startTime = microtime(true);

if ($isFile) {
    $result = $migrator->migrateFile($target);
    
    if ($result->success) {
        echo "✓ Successfully processed: {$target}\n";
        if ($result->modified) {
            echo "  - Parameters updated: {$result->parametersUpdated}\n";
            echo "  - Return types added: {$result->returnTypesAdded}\n";
            echo "  - Property types added: {$result->propertyTypesAdded}\n";
            
            if (!empty($result->flaggedItems)) {
                echo "  - Items flagged for review: " . count($result->flaggedItems) . "\n";
            }
        } else {
            echo "  - No changes needed\n";
        }
    } else {
        echo "✗ Error processing: {$target}\n";
        echo "  Error: {$result->error}\n";
        exit(1);
    }
} else {
    $result = $migrator->migrateDirectory($target);
    
    echo "Migration complete!\n\n";
    echo "Summary:\n";
    echo "  Files processed: {$result->filesProcessed}\n";
    echo "  Files modified: {$result->filesModified}\n";
    echo "  Parameters updated: {$result->parametersUpdated}\n";
    echo "  Return types added: {$result->returnTypesAdded}\n";
    echo "  Property types added: {$result->propertyTypesAdded}\n";
    
    if (!empty($result->flaggedForReview)) {
        echo "  Files flagged for review: " . count($result->flaggedForReview) . "\n";
    }
    
    if (!empty($result->errors)) {
        echo "\nErrors:\n";
        foreach ($result->errors as $file => $error) {
            echo "  - {$file}: {$error}\n";
        }
    }
}

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\nCompleted in {$duration} seconds\n";

if ($autoFormat && !$dryRun) {
    echo "\nNote: Files have been automatically formatted with PHP-CS-Fixer to restore proper whitespace.\n";
}

if ($dryRun) {
    echo "\nThis was a dry run. No files were modified.\n";
    echo "Run without --dry-run to apply changes.\n";
}
