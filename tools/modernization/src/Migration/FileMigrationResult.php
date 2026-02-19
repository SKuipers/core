<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Migration;

/**
 * Result of migrating a single file
 * 
 * Contains information about the file migration including
 * success status, modifications made, and any errors.
 */
class FileMigrationResult
{
    public function __construct(
        public readonly string $filePath,
        public readonly bool $success,
        public readonly bool $modified,
        public readonly int $parametersUpdated = 0,
        public readonly int $returnTypesAdded = 0,
        public readonly int $propertyTypesAdded = 0,
        public readonly array $flaggedItems = [],
        public readonly ?string $backupPath = null,
        public readonly ?string $error = null,
        public readonly ?string $modifiedCode = null
    ) {}

    /**
     * Get total number of type hints added
     */
    public function getTotalTypeHintsAdded(): int
    {
        return $this->parametersUpdated + $this->returnTypesAdded + $this->propertyTypesAdded;
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'filePath' => $this->filePath,
            'success' => $this->success,
            'modified' => $this->modified,
            'parametersUpdated' => $this->parametersUpdated,
            'returnTypesAdded' => $this->returnTypesAdded,
            'propertyTypesAdded' => $this->propertyTypesAdded,
            'totalTypeHintsAdded' => $this->getTotalTypeHintsAdded(),
            'flaggedItems' => $this->flaggedItems,
            'backupPath' => $this->backupPath,
            'error' => $this->error,
            'hasModifiedCode' => $this->modifiedCode !== null
        ];
    }
}

