<?php

namespace Gibbon\Modernization\Config;

/**
 * Configuration for the modernization tool
 */
class MigrationConfig
{
    public function __construct(
        public readonly string $rootPath,
        public readonly array $includePaths = ['src/'],
        public readonly array $excludePaths = ['vendor/', 'tests/'],
        public readonly bool $dryRun = false,
        public readonly bool $createBackups = true,
        public readonly string $backupPath = 'backups',
        public readonly float $confidenceThreshold = 0.7,
        public readonly bool $useMixedForLowConfidence = true,
        public readonly int $batchSize = 50,
        public readonly bool $runValidation = true,
        public readonly int $phpstanLevel = 6,
        public readonly string $phpstanConfig = 'phpstan.neon',
        public readonly string $reportPath = 'reports',
        public readonly array $reportFormats = ['console', 'json']
    ) {}
    
    /**
     * Create configuration from array
     */
    public static function fromArray(array $config): self
    {
        return new self(
            rootPath: $config['rootPath'] ?? getcwd(),
            includePaths: $config['includePaths'] ?? ['src/'],
            excludePaths: $config['excludePaths'] ?? ['vendor/', 'tests/'],
            dryRun: $config['dryRun'] ?? false,
            createBackups: $config['createBackups'] ?? true,
            backupPath: $config['backupPath'] ?? 'backups',
            confidenceThreshold: $config['confidenceThreshold'] ?? 0.7,
            useMixedForLowConfidence: $config['useMixedForLowConfidence'] ?? true,
            batchSize: $config['batchSize'] ?? 50,
            runValidation: $config['runValidation'] ?? true,
            phpstanLevel: $config['phpstanLevel'] ?? 6,
            phpstanConfig: $config['phpstanConfig'] ?? 'phpstan.neon',
            reportPath: $config['reportPath'] ?? 'reports',
            reportFormats: $config['reportFormats'] ?? ['console', 'json']
        );
    }
    
    /**
     * Load configuration from file
     */
    public static function fromFile(string $configPath): self
    {
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Configuration file not found: {$configPath}");
        }
        
        $config = require $configPath;
        
        if (!is_array($config)) {
            throw new \RuntimeException("Configuration file must return an array");
        }
        
        return self::fromArray($config);
    }
    
    /**
     * Convert configuration to array
     */
    public function toArray(): array
    {
        return [
            'rootPath' => $this->rootPath,
            'includePaths' => $this->includePaths,
            'excludePaths' => $this->excludePaths,
            'dryRun' => $this->dryRun,
            'createBackups' => $this->createBackups,
            'backupPath' => $this->backupPath,
            'confidenceThreshold' => $this->confidenceThreshold,
            'useMixedForLowConfidence' => $this->useMixedForLowConfidence,
            'batchSize' => $this->batchSize,
            'runValidation' => $this->runValidation,
            'phpstanLevel' => $this->phpstanLevel,
            'phpstanConfig' => $this->phpstanConfig,
            'reportPath' => $this->reportPath,
            'reportFormats' => $this->reportFormats,
        ];
    }
}
