<?php

declare(strict_types=1);

namespace Gibbon\Modernization\TypeInference;

/**
 * InferredType - Represents a type inferred from multiple sources
 * 
 * Contains the inferred type string, whether it's nullable, confidence score,
 * and the source of the inference (PHPDoc, inheritance, or usage analysis).
 * 
 * Requirements: 2.1, 2.2, 3.1, 3.2, 3.3, 4.1, 4.2, 5.1, 5.2
 */
class InferredType
{
    /**
     * @param string $type The inferred type (e.g., 'string', 'int|float', 'array')
     * @param bool $isNullable Whether the type is nullable
     * @param float $confidence Confidence level (0.0 to 1.0)
     * @param string $source Source of inference ('phpdoc', 'inheritance', 'usage', 'fallback')
     */
    public function __construct(
        public readonly string $type,
        public readonly bool $isNullable,
        public readonly float $confidence,
        public readonly string $source
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
     * Convert to PHP type hint string
     * 
     * @return string Type hint string (e.g., '?string', 'int|float', 'mixed')
     */
    public function toString(): string
    {
        if ($this->type === 'void' || $this->type === 'mixed' || $this->type === 'null') {
            return $this->type;
        }

        if ($this->isNullable) {
            // Use nullable shorthand for single types
            if (!str_contains($this->type, '|')) {
                return '?' . $this->type;
            }
            
            // For union types, add null to the union
            if (!str_contains($this->type, 'null')) {
                return $this->type . '|null';
            }
        }

        return $this->type;
    }

    /**
     * Convert to array representation
     * 
     * @return array{type: string, isNullable: bool, confidence: float, source: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'isNullable' => $this->isNullable,
            'confidence' => $this->confidence,
            'source' => $this->source,
        ];
    }
}
