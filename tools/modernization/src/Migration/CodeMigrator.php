<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Migration;

use Gibbon\Modernization\Config\MigrationConfig;
use Gibbon\Modernization\Scanner\ASTParser;
use Gibbon\Modernization\TypeInference\InferredType;
use Gibbon\Modernization\TypeInference\TypeInferenceEngine;
use PhpParser\Node;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * CodeMigrator - Orchestrates the migration process
 * 
 * Coordinates backup, type inference, transformation, and result tracking
 * for PHP 8.4 modernization. Supports single file and batch directory
 * processing with dry-run mode.
 * 
 * Requirements: 2.1, 2.2, 2.6, 10.1, 10.2, 10.3
 */
class CodeMigrator
{
    public function __construct(
        private TypeInferenceEngine $typeInference,
        private CodeTransformer $transformer,
        private BackupManager $backupManager,
        private MigrationConfig $config
    ) {}

    /**
     * Migrate a single file
     * 
     * Process: backup → infer → transform → write
     * 
     * @param string $filePath Path to file to migrate
     * @return FileMigrationResult Result of migration
     */
    public function migrateFile(string $filePath): FileMigrationResult
    {
        if (!file_exists($filePath)) {
            return new FileMigrationResult(
                filePath: $filePath,
                success: false,
                modified: false,
                error: "File not found: {$filePath}"
            );
        }

        try {
            // Step 1: Backup
            $backupPath = null;
            if ($this->config->createBackups && !$this->config->dryRun) {
                $backupPath = $this->backupManager->backupFile($filePath);
            }

            // Step 2: Read and parse
            $originalCode = file_get_contents($filePath);
            if ($originalCode === false) {
                return new FileMigrationResult(
                    filePath: $filePath,
                    success: false,
                    modified: false,
                    error: "Failed to read file: {$filePath}"
                );
            }

            $parser = new ASTParser();
            $parseResult = $parser->parseCode($originalCode);
            
            if (!$parseResult->isSuccess()) {
                return new FileMigrationResult(
                    filePath: $filePath,
                    success: false,
                    modified: false,
                    error: "Failed to parse file: {$filePath}"
                );
            }

            // Step 3: Infer and transform
            $result = $this->processFile($filePath, $originalCode, $parseResult->getAst());

            // Step 4: Write (if not dry-run and modified)
            if (!$this->config->dryRun && $result->modified && $result->modifiedCode !== null) {
                if (file_put_contents($filePath, $result->modifiedCode) === false) {
                    return new FileMigrationResult(
                        filePath: $filePath,
                        success: false,
                        modified: false,
                        error: "Failed to write file: {$filePath}",
                        backupPath: $backupPath
                    );
                }
            }

            return new FileMigrationResult(
                filePath: $filePath,
                success: true,
                modified: $result->modified,
                parametersUpdated: $result->parametersUpdated,
                returnTypesAdded: $result->returnTypesAdded,
                propertyTypesAdded: $result->propertyTypesAdded,
                flaggedItems: $result->flaggedItems,
                backupPath: $backupPath,
                modifiedCode: $this->config->dryRun ? $result->modifiedCode : null
            );
        } catch (\Exception $e) {
            return new FileMigrationResult(
                filePath: $filePath,
                success: false,
                modified: false,
                error: $e->getMessage()
            );
        }
    }

    /**
     * Migrate a directory with batch processing
     * 
     * @param string $path Directory path to migrate
     * @param array $options Additional options
     * @return MigrationResult Result of migration
     */
    public function migrateDirectory(string $path, array $options = []): MigrationResult
    {
        if (!is_dir($path)) {
            throw new RuntimeException("Directory not found: {$path}");
        }

        $files = $this->collectPhpFiles($path);
        $batchSize = $options['batchSize'] ?? $this->config->batchSize;
        
        $filesProcessed = 0;
        $filesModified = 0;
        $parametersUpdated = 0;
        $returnTypesAdded = 0;
        $propertyTypesAdded = 0;
        $flaggedForReview = [];
        $errors = [];

        // Process in batches
        $batches = array_chunk($files, $batchSize);
        
        foreach ($batches as $batch) {
            foreach ($batch as $file) {
                $result = $this->migrateFile($file);
                $filesProcessed++;

                if ($result->success) {
                    if ($result->modified) {
                        $filesModified++;
                        $parametersUpdated += $result->parametersUpdated;
                        $returnTypesAdded += $result->returnTypesAdded;
                        $propertyTypesAdded += $result->propertyTypesAdded;
                    }
                    
                    if (!empty($result->flaggedItems)) {
                        $flaggedForReview[$file] = $result->flaggedItems;
                    }
                } else {
                    $errors[$file] = $result->error ?? 'Unknown error';
                }
            }
        }

        return new MigrationResult(
            filesProcessed: $filesProcessed,
            filesModified: $filesModified,
            parametersUpdated: $parametersUpdated,
            returnTypesAdded: $returnTypesAdded,
            propertyTypesAdded: $propertyTypesAdded,
            flaggedForReview: $flaggedForReview,
            errors: $errors
        );
    }

    /**
     * Perform a dry-run migration preview
     * 
     * @param string $path File or directory path
     * @return MigrationResult Preview of changes
     */
    public function dryRun(string $path): MigrationResult
    {
        // Temporarily enable dry-run mode
        $originalDryRun = $this->config->dryRun;
        $config = new MigrationConfig(
            rootPath: $this->config->rootPath,
            includePaths: $this->config->includePaths,
            excludePaths: $this->config->excludePaths,
            dryRun: true,
            createBackups: false,
            backupPath: $this->config->backupPath,
            confidenceThreshold: $this->config->confidenceThreshold,
            useMixedForLowConfidence: $this->config->useMixedForLowConfidence,
            batchSize: $this->config->batchSize,
            runValidation: $this->config->runValidation,
            phpstanLevel: $this->config->phpstanLevel,
            phpstanConfig: $this->config->phpstanConfig,
            reportPath: $this->config->reportPath,
            reportFormats: $this->config->reportFormats
        );

        // Create temporary migrator with dry-run config
        $dryRunMigrator = new self(
            $this->typeInference,
            $this->transformer,
            $this->backupManager,
            $config
        );

        if (is_file($path)) {
            $result = $dryRunMigrator->migrateFile($path);
            return new MigrationResult(
                filesProcessed: 1,
                filesModified: $result->modified ? 1 : 0,
                parametersUpdated: $result->parametersUpdated,
                returnTypesAdded: $result->returnTypesAdded,
                propertyTypesAdded: $result->propertyTypesAdded,
                flaggedForReview: !empty($result->flaggedItems) ? [$path => $result->flaggedItems] : [],
                errors: $result->success ? [] : [$path => $result->error ?? 'Unknown error']
            );
        }

        return $dryRunMigrator->migrateDirectory($path);
    }

    /**
     * Process a single file's AST and apply transformations
     */
    private function processFile(string $filePath, string $code, array $ast): FileProcessResult
    {
        $modified = false;
        $parametersUpdated = 0;
        $returnTypesAdded = 0;
        $propertyTypesAdded = 0;
        $flaggedItems = [];
        $currentCode = $code;

        // Extract class name if present
        $className = $this->extractClassName($ast);

        // Process parameters (including implicitly nullable)
        foreach ($ast as $node) {
            $this->processNode(
                $node,
                $currentCode,
                $className,
                $modified,
                $parametersUpdated,
                $returnTypesAdded,
                $propertyTypesAdded,
                $flaggedItems
            );
        }

        return new FileProcessResult(
            modified: $modified,
            parametersUpdated: $parametersUpdated,
            returnTypesAdded: $returnTypesAdded,
            propertyTypesAdded: $propertyTypesAdded,
            flaggedItems: $flaggedItems,
            modifiedCode: $modified ? $currentCode : null
        );
    }

    /**
     * Process a node recursively
     */
    private function processNode(
        Node $node,
        string &$code,
        ?string $className,
        bool &$modified,
        int &$parametersUpdated,
        int &$returnTypesAdded,
        int &$propertyTypesAdded,
        array &$flaggedItems
    ): void {
        // Process class methods
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->processMethod($node, $code, $className, $modified, $parametersUpdated, $returnTypesAdded, $flaggedItems);
        }

        // Process functions
        if ($node instanceof Node\Stmt\Function_) {
            $this->processFunction($node, $code, $modified, $parametersUpdated, $returnTypesAdded, $flaggedItems);
        }

        // Process properties
        if ($node instanceof Node\Stmt\Property) {
            $this->processProperty($node, $code, $className, $modified, $propertyTypesAdded, $flaggedItems);
        }

        // Recurse into child nodes
        foreach ($node->getSubNodeNames() as $name) {
            $subNode = $node->$name;
            
            if ($subNode instanceof Node) {
                $this->processNode($subNode, $code, $className, $modified, $parametersUpdated, $returnTypesAdded, $propertyTypesAdded, $flaggedItems);
            } elseif (is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
                        $this->processNode($item, $code, $className, $modified, $parametersUpdated, $returnTypesAdded, $propertyTypesAdded, $flaggedItems);
                    }
                }
            }
        }
    }

    /**
     * Process a method node
     */
    private function processMethod(
        Node\Stmt\ClassMethod $method,
        string &$code,
        ?string $className,
        bool &$modified,
        int &$parametersUpdated,
        int &$returnTypesAdded,
        array &$flaggedItems
    ): void {
        $methodName = $method->name->toString();

        // Process parameters
        foreach ($method->params as $param) {
            if ($param->type === null) {
                $inferredType = $this->typeInference->inferParameterType($param, $method, $className);
                
                if ($this->shouldApplyType($inferredType)) {
                    $code = $this->transformer->addParameterType($code, $param->var->name, $inferredType, $methodName);
                    $modified = true;
                    $parametersUpdated++;
                } else {
                    $flaggedItems[] = [
                        'type' => 'parameter',
                        'method' => $methodName,
                        'parameter' => $param->var->name,
                        'reason' => 'Low confidence type inference',
                        'suggested' => $inferredType->type,
                        'confidence' => $inferredType->confidence
                    ];
                }
            }
        }

        // Process return type
        if ($method->returnType === null) {
            $inferredType = $this->typeInference->inferReturnType($method, $className);
            
            if ($this->shouldApplyType($inferredType)) {
                $code = $this->transformer->addReturnType($code, $methodName, $inferredType);
                $modified = true;
                $returnTypesAdded++;
            } else {
                $flaggedItems[] = [
                    'type' => 'return',
                    'method' => $methodName,
                    'reason' => 'Low confidence type inference',
                    'suggested' => $inferredType->type,
                    'confidence' => $inferredType->confidence
                ];
            }
        }
    }

    /**
     * Process a function node
     */
    private function processFunction(
        Node\Stmt\Function_ $function,
        string &$code,
        bool &$modified,
        int &$parametersUpdated,
        int &$returnTypesAdded,
        array &$flaggedItems
    ): void {
        $functionName = $function->name->toString();

        // Process parameters
        foreach ($function->params as $param) {
            if ($param->type === null) {
                $inferredType = $this->typeInference->inferParameterType($param, $function, null);
                
                if ($this->shouldApplyType($inferredType)) {
                    $code = $this->transformer->addParameterType($code, $param->var->name, $inferredType, $functionName);
                    $modified = true;
                    $parametersUpdated++;
                } else {
                    $flaggedItems[] = [
                        'type' => 'parameter',
                        'function' => $functionName,
                        'parameter' => $param->var->name,
                        'reason' => 'Low confidence type inference',
                        'suggested' => $inferredType->type,
                        'confidence' => $inferredType->confidence
                    ];
                }
            }
        }

        // Process return type
        if ($function->returnType === null) {
            $inferredType = $this->typeInference->inferReturnType($function, null);
            
            if ($this->shouldApplyType($inferredType)) {
                $code = $this->transformer->addReturnType($code, $functionName, $inferredType);
                $modified = true;
                $returnTypesAdded++;
            } else {
                $flaggedItems[] = [
                    'type' => 'return',
                    'function' => $functionName,
                    'reason' => 'Low confidence type inference',
                    'suggested' => $inferredType->type,
                    'confidence' => $inferredType->confidence
                ];
            }
        }
    }

    /**
     * Process a property node
     */
    private function processProperty(
        Node\Stmt\Property $property,
        string &$code,
        ?string $className,
        bool &$modified,
        int &$propertyTypesAdded,
        array &$flaggedItems
    ): void {
        if ($property->type === null && !empty($property->props)) {
            $propertyName = $property->props[0]->name->toString();
            
            // Need class node for property inference
            // For now, skip if we can't get class context
            // This would be improved with better AST traversal
            $flaggedItems[] = [
                'type' => 'property',
                'property' => $propertyName,
                'reason' => 'Property type inference requires class context',
                'suggested' => 'mixed'
            ];
        }
    }

    /**
     * Extract class name from AST
     */
    private function extractClassName(array $ast): ?string
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $namespace = $node->name ? $node->name->toString() : '';
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Class_) {
                        $className = $stmt->name->toString();
                        return $namespace ? $namespace . '\\' . $className : $className;
                    }
                }
            } elseif ($node instanceof Node\Stmt\Class_) {
                return $node->name->toString();
            }
        }
        return null;
    }

    /**
     * Collect PHP files from directory
     */
    private function collectPhpFiles(string $path): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filePath = $file->getPathname();
                
                // Check exclude patterns
                $excluded = false;
                foreach ($this->config->excludePaths as $excludePattern) {
                    if (str_contains($filePath, $excludePattern)) {
                        $excluded = true;
                        break;
                    }
                }
                
                if (!$excluded) {
                    $files[] = $filePath;
                }
            }
        }

        return $files;
    }

    /**
     * Determine if a type should be applied based on confidence
     */
    private function shouldApplyType(InferredType $type): bool
    {
        // Always apply if confidence meets threshold
        if ($type->confidence >= $this->config->confidenceThreshold) {
            return true;
        }

        // Apply 'mixed' type if configured for low confidence
        if ($this->config->useMixedForLowConfidence && $type->type === 'mixed') {
            return true;
        }

        return false;
    }
}

