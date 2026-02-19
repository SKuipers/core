<?php

declare(strict_types=1);

namespace Gibbon\Modernization\TypeInference;

/**
 * TypeUsageInfo - Contains information about how a type is used
 * 
 * Stores the inferred type, confidence level, and usage patterns
 * that led to the inference.
 */
class TypeUsageInfo
{
    /**
     * @param string $inferredType The inferred type (e.g., 'string', 'int|float', 'array')
     * @param float $confidence Confidence level (0.0 to 1.0)
     * @param array<array{type: string, operation: string}> $usages Array of usage patterns
     */
    public function __construct(
        public readonly string $inferredType,
        public readonly float $confidence,
        public readonly array $usages
    ) {}

    /**
     * Check if the inference is confident enough to use
     * 
     * @param float $threshold Confidence threshold (default 0.7)
     * @return bool True if confidence meets threshold
     */
    public function isConfident(float $threshold = 0.7): bool
    {
        return $this->confidence >= $threshold;
    }

    /**
     * Get a human-readable description of the usage patterns
     * 
     * @return string Description of usage patterns
     */
    public function getUsageDescription(): string
    {
        if (empty($this->usages)) {
            return 'No usage patterns found';
        }

        $descriptions = [];
        foreach ($this->usages as $usage) {
            $descriptions[] = "{$usage['type']}: {$usage['operation']}";
        }

        return implode(', ', $descriptions);
    }

    /**
     * Convert to array representation
     * 
     * @return array{inferredType: string, confidence: float, usages: array}
     */
    public function toArray(): array
    {
        return [
            'inferredType' => $this->inferredType,
            'confidence' => $this->confidence,
            'usages' => $this->usages,
        ];
    }
}
