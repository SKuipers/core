#!/usr/bin/env php
<?php
/**
 * Run PHP 8.4 Modernization on src directory
 * 
 * Usage:
 *   php tools/modernization/run-migration.php [--dry-run] [--path=src/Module]
 */

require_once __DIR__ . '/vendor/autoload.php';

use Gibbon\Modernization\Config\MigrationConfig;
use Gibbon\Modernization\Migration\BackupManager;
use Gibbon\Modernization\Migration\CodeMigrator;
use Gibbon\Modernization\Migration\CodeTransformer;
use Gibbon\Modernization\TypeInference\InheritanceAnalyzer;
use Gibbon\Modernization\TypeInference\PHPDocAnalyzer;
use Gibbon\Modernization\TypeInference\TypeInferenceEngine;
use Gibbon\Modernization\TypeInference\UsageAnalyzer;

// Parse command line arguments
$options = getopt('', ['dry-run', 'path:']);
$dryRun = isset($options['dry-run']);
$targetPath = $options['path'] ?? 'src';

// Get workspace root (2 levels up from tools/modernization)
$workspaceRoot = dirname(dirname(__DIR__));
$fullPath = $workspaceRoot . '/' . $targetPath;

if (!file_exists($fullPath)) {
    echo "Error: Path not found: {$fullPath}\n";
    exit(1);
}

echo "PHP 8.4 Modernization Tool\n";
echo "==========================\n\n";
echo "Target: {$targetPath}\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no files will be modified)" : "LIVE (files will be modified)") . "\n";
echo "\n";

if (!$dryRun) {
    echo "WARNING: This will modify your source files!\n";
    echo "Backups will be created in: {$workspaceRoot}/backups/\n";
    echo "\nPress Enter to continue or Ctrl+C to cancel...";
    fgets(STDIN);
    echo "\n";
}

// Create configuration
$config = new MigrationConfig(
    rootPath: $workspaceRoot,
    includePaths: [$targetPath],
    excludePaths: ['vendor/', 'tests/', 'node_modules/'],
    dryRun: $dryRun,
    createBackups: !$dryRun,
    backupPath: $workspaceRoot . '/backups',
    confidenceThreshold: 0.7,
    useMixedForLowConfidence: true,
    batchSize: 10
);

// Initialize components
$phpDocAnalyzer = new PHPDocAnalyzer();
$usageAnalyzer = new UsageAnalyzer();
$inheritanceAnalyzer = new InheritanceAnalyzer($phpDocAnalyzer);
$typeInference = new TypeInferenceEngine($phpDocAnalyzer, $usageAnalyzer, $inheritanceAnalyzer);
$transformer = new CodeTransformer();
$backupManager = new BackupManager($config->backupPath);

// Create migrator
$migrator = new CodeMigrator($typeInference, $transformer, $backupManager, $config);

// Run migration
echo "Starting migration...\n\n";
$startTime = microtime(true);

try {
    if (is_file($fullPath)) {
        $result = $migrator->migrateFile($fullPath);
        
        echo "File: {$targetPath}\n";
        echo "  Status: " . ($result->success ? "✓ Success" : "✗ Failed") . "\n";
        
        if ($result->modified) {
            echo "  Modified: Yes\n";
            echo "  Parameters updated: {$result->parametersUpdated}\n";
            echo "  Return types added: {$result->returnTypesAdded}\n";
            echo "  Property types added: {$result->propertyTypesAdded}\n";
            
            if (!empty($result->flaggedItems)) {
                echo "  Flagged for review: " . count($result->flaggedItems) . " items\n";
            }
        } else {
            echo "  Modified: No\n";
        }
        
        if ($result->error) {
            echo "  Error: {$result->error}\n";
        }
    } else {
        $result = $migrator->migrateDirectory($fullPath);
        
        echo "Migration Results:\n";
        echo "==================\n";
        echo "Files processed: {$result->filesProcessed}\n";
        echo "Files modified: {$result->filesModified}\n";
        echo "Parameters updated: {$result->parametersUpdated}\n";
        echo "Return types added: {$result->returnTypesAdded}\n";
        echo "Property types added: {$result->propertyTypesAdded}\n";
        echo "Total type hints added: {$result->getTotalTypeHintsAdded()}\n";
        
        if (!empty($result->flaggedForReview)) {
            echo "\nFlagged for Manual Review:\n";
            echo "--------------------------\n";
            foreach ($result->flaggedForReview as $file => $items) {
                echo "\n{$file}:\n";
                foreach ($items as $item) {
                    echo "  - {$item['type']}: ";
                    if (isset($item['method'])) {
                        echo "{$item['method']}";
                    } elseif (isset($item['function'])) {
                        echo "{$item['function']}";
                    }
                    if (isset($item['parameter'])) {
                        echo " (parameter: {$item['parameter']})";
                    }
                    echo "\n";
                    echo "    Reason: {$item['reason']}\n";
                    echo "    Suggested: {$item['suggested']} (confidence: " . 
                         number_format($item['confidence'] ?? 0, 2) . ")\n";
                }
            }
        }
        
        if (!empty($result->errors)) {
            echo "\nErrors:\n";
            echo "-------\n";
            foreach ($result->errors as $file => $error) {
                echo "{$file}: {$error}\n";
            }
        }
    }
    
    $duration = microtime(true) - $startTime;
    echo "\n";
    echo "Completed in " . number_format($duration, 2) . " seconds\n";
    
    if ($dryRun) {
        echo "\nThis was a dry run. No files were modified.\n";
        echo "Run without --dry-run to apply changes.\n";
    } else {
        echo "\nMigration complete! Backups saved to: {$config->backupPath}\n";
    }
    
} catch (Exception $e) {
    echo "\nError: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
