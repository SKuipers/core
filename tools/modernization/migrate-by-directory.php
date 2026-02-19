#!/usr/bin/env php
<?php
/**
 * Migrate PHP files directory by directory
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

$dryRun = in_array('--dry-run', $argv);
$apply = in_array('--apply', $argv);

$workspaceRoot = dirname(dirname(__DIR__));

// Get all subdirectories in src/
$srcDir = $workspaceRoot . '/src';
$directories = array_filter(glob($srcDir . '/*'), 'is_dir');

echo "PHP 8.4 Modernization - Directory by Directory\n";
echo "===============================================\n\n";
echo "Mode: " . ($dryRun ? "DRY RUN" : ($apply ? "APPLY CHANGES" : "PREVIEW")) . "\n\n";

if ($apply && !$dryRun) {
    echo "WARNING: This will modify your source files!\n";
    echo "Press Enter to continue or Ctrl+C to cancel...";
    fgets(STDIN);
    echo "\n";
}

// Initialize components once
$config = new MigrationConfig(
    rootPath: $workspaceRoot,
    includePaths: ['src/'],
    excludePaths: ['vendor/', 'tests/'],
    dryRun: $dryRun || !$apply,
    createBackups: $apply && !$dryRun,
    backupPath: $workspaceRoot . '/backups',
    confidenceThreshold: 0.7,
    useMixedForLowConfidence: true,
    batchSize: 10
);

$phpDocAnalyzer = new PHPDocAnalyzer();
$usageAnalyzer = new UsageAnalyzer();
$inheritanceAnalyzer = new InheritanceAnalyzer($phpDocAnalyzer);
$typeInference = new TypeInferenceEngine($phpDocAnalyzer, $usageAnalyzer, $inheritanceAnalyzer);
$transformer = new CodeTransformer();
$backupManager = new BackupManager($config->backupPath);
$migrator = new CodeMigrator($typeInference, $transformer, $backupManager, $config);

$totalStats = [
    'filesProcessed' => 0,
    'filesModified' => 0,
    'parametersUpdated' => 0,
    'returnTypesAdded' => 0,
    'propertyTypesAdded' => 0,
];

foreach ($directories as $dir) {
    $dirName = basename($dir);
    $relativePath = 'src/' . $dirName;
    
    echo "Processing: {$relativePath}\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        $result = $migrator->migrateDirectory($dir);
        
        echo "  Files processed: {$result->filesProcessed}\n";
        echo "  Files modified: {$result->filesModified}\n";
        echo "  Parameters updated: {$result->parametersUpdated}\n";
        echo "  Return types added: {$result->returnTypesAdded}\n";
        echo "  Property types added: {$result->propertyTypesAdded}\n";
        echo "  Total type hints: {$result->getTotalTypeHintsAdded()}\n";
        
        if (!empty($result->flaggedForReview)) {
            echo "  Flagged items: " . count($result->flaggedForReview) . " files\n";
        }
        
        if (!empty($result->errors)) {
            echo "  Errors: " . count($result->errors) . " files\n";
        }
        
        $totalStats['filesProcessed'] += $result->filesProcessed;
        $totalStats['filesModified'] += $result->filesModified;
        $totalStats['parametersUpdated'] += $result->parametersUpdated;
        $totalStats['returnTypesAdded'] += $result->returnTypesAdded;
        $totalStats['propertyTypesAdded'] += $result->propertyTypesAdded;
        
    } catch (Exception $e) {
        echo "  ERROR: {$e->getMessage()}\n";
    }
    
    echo "\n";
}

echo "\n";
echo "TOTAL SUMMARY\n";
echo "=============\n";
echo "Files processed: {$totalStats['filesProcessed']}\n";
echo "Files modified: {$totalStats['filesModified']}\n";
echo "Parameters updated: {$totalStats['parametersUpdated']}\n";
echo "Return types added: {$totalStats['returnTypesAdded']}\n";
echo "Property types added: {$totalStats['propertyTypesAdded']}\n";
$totalTypeHints = $totalStats['parametersUpdated'] + $totalStats['returnTypesAdded'] + $totalStats['propertyTypesAdded'];
echo "Total type hints added: {$totalTypeHints}\n";

if (!$apply || $dryRun) {
    echo "\nNo files were modified. Use --apply to make changes.\n";
} else {
    echo "\nMigration complete! Backups saved to: {$config->backupPath}\n";
}
