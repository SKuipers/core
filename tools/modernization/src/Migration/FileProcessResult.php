<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Migration;

/**
 * Internal result of processing a file's AST
 * 
 * Used internally by CodeMigrator to track modifications
 * during the transformation process.
 */
class FileProcessResult
{
    public function __construct(
        public readonly bool $modified,
        public readonly int $parametersUpdated,
        public readonly int $returnTypesAdded,
        public readonly int $propertyTypesAdded,
        public readonly array $flaggedItems,
        public readonly ?string $modifiedCode
    ) {}
}

