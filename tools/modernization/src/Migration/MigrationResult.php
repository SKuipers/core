<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Migration;

/**
 * Result of a migration operation
 * 
 * Contains statistics and information about files processed,
 * modifications made, and items flagged for manual review.
 */
class MigrationResult
{
    public function __construct(
        public readonly int $filesProcessed,
        public readonly int $filesModified,
        public readonly int $parametersUpdated,
        public readonly int $returnTypesAdded,
        public readonly int $propertyTypesAdded,
        public readonly array $flaggedForReview,
        public readonly array $errors
    ) {}

    /**
     * Check if migration was successful
     */
    public function isSuccessful(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get total number of type hints added
     */
    public function getTotalTypeHintsAdded(): int
    {
        return $this->parametersUpdated + $this->returnTypesAdded + $this->propertyTypesAdded;
    }

    /**
     * Get total number of items flagged for review
     */
    public function getFlaggedCount(): int
    {
        $count = 0;
        foreach ($this->flaggedForReview as $items) {
            $count += count($items);
        }
        return $count;
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'filesProcessed' => $this->filesProcessed,
            'filesModified' => $this->filesModified,
            'parametersUpdated' => $this->parametersUpdated,
            'returnTypesAdded' => $this->returnTypesAdded,
            'propertyTypesAdded' => $this->propertyTypesAdded,
            'totalTypeHintsAdded' => $this->getTotalTypeHintsAdded(),
            'flaggedForReview' => $this->flaggedForReview,
            'flaggedCount' => $this->getFlaggedCount(),
            'errors' => $this->errors,
            'successful' => $this->isSuccessful()
        ];
    }
}

