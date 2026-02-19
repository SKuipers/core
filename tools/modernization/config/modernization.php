<?php
/**
 * Default configuration for PHP 8.4 Modernization Tool
 */

return [
    // Root path of the project to modernize (relative to this config file)
    'rootPath' => dirname(__DIR__, 3),
    
    // Paths to include in scanning/migration (relative to rootPath)
    'includePaths' => [
        'src/',
        'modules/',
        'lib/',
    ],
    
    // Paths to exclude from scanning/migration (relative to rootPath)
    'excludePaths' => [
        'vendor/',
        'tests/',
        'uploads/',
        'resources/',
    ],
    
    // Dry run mode - preview changes without modifying files
    'dryRun' => false,
    
    // Create backups before modifying files
    'createBackups' => true,
    
    // Backup directory (relative to rootPath)
    'backupPath' => 'tools/modernization/backups',
    
    // Confidence threshold for automatic type inference (0.0 - 1.0)
    // Types with confidence below this will be flagged for manual review
    'confidenceThreshold' => 0.7,
    
    // Use 'mixed' type for low confidence inferences
    'useMixedForLowConfidence' => true,
    
    // Batch size for processing files
    'batchSize' => 50,
    
    // Run validation after migration
    'runValidation' => true,
    
    // PHPStan analysis level (0-9)
    'phpstanLevel' => 6,
    
    // PHPStan configuration file (relative to rootPath)
    'phpstanConfig' => 'phpstan.neon',
    
    // Report output directory (relative to rootPath)
    'reportPath' => 'tools/modernization/reports',
    
    // Report formats to generate
    'reportFormats' => ['console', 'json', 'html'],
];
