<?php

declare(strict_types=1);

namespace Gibbon\Modernization\TypeInference;

use PhpParser\Comment\Doc;

/**
 * PHPDocAnalyzer - Extracts type information from PHPDoc comments
 * 
 * Parses PHPDoc annotations to extract @param, @return, and @var type information.
 * Handles union types, nullable types, and array shapes.
 * 
 * Requirements: 2.1, 2.3, 3.1, 4.1, 5.1
 */
class PHPDocAnalyzer
{
    /**
     * Extract parameter type from PHPDoc comment
     * 
     * @param string|null $phpDoc PHPDoc comment string
     * @param string $paramName Parameter name (without $)
     * @return string|null The type string or null if not found
     */
    public function extractParameterType(?string $phpDoc, string $paramName): ?string
    {
        if ($phpDoc === null || $phpDoc === '') {
            return null;
        }

        // Match @param type $paramName patterns
        // Type can include generics like array<string, int>
        $pattern = '/@param\s+([^\$]+?)\s+\$' . preg_quote($paramName, '/') . '(?:\s|$)/';
        
        if (preg_match($pattern, $phpDoc, $matches)) {
            return $this->normalizeType(trim($matches[1]));
        }

        return null;
    }

    /**
     * Extract return type from PHPDoc comment
     * 
     * @param string|null $phpDoc PHPDoc comment string
     * @return string|null The type string or null if not found
     */
    public function extractReturnType(?string $phpDoc): ?string
    {
        if ($phpDoc === null || $phpDoc === '') {
            return null;
        }

        // Match @return type patterns
        // Type can include generics like array<string, int>, union types, etc.
        // Pattern handles generics with spaces: array<string, int>
        $pattern = '/@return\s+([^\s<]+<[^>]+>|[^\s]+)/';
        
        if (preg_match($pattern, $phpDoc, $matches)) {
            return $this->normalizeType(trim($matches[1]));
        }

        return null;
    }

    /**
     * Extract property type from PHPDoc comment
     * 
     * @param string|null $phpDoc PHPDoc comment string
     * @return string|null The type string or null if not found
     */
    public function extractPropertyType(?string $phpDoc): ?string
    {
        if ($phpDoc === null || $phpDoc === '') {
            return null;
        }

        // Match @var type patterns
        // Type can include generics like array<string, int>, union types, etc.
        // Pattern handles generics with spaces: array<string, int>
        $pattern = '/@var\s+([^\s<]+<[^>]+>|[^\s]+)/';
        
        if (preg_match($pattern, $phpDoc, $matches)) {
            return $this->normalizeType(trim($matches[1]));
        }

        return null;
    }

    /**
     * Extract PHPDoc comment from a node
     * 
     * @param mixed $node PHP-Parser node
     * @return string|null The PHPDoc comment text or null
     */
    public function extractPhpDocFromNode($node): ?string
    {
        if (!is_object($node) || !method_exists($node, 'getDocComment')) {
            return null;
        }

        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return null;
        }

        return $docComment->getText();
    }

    /**
     * Normalize type string to PHP 8.4 compatible format
     * 
     * Handles:
     * - Union types (string|int)
     * - Nullable types (?string or string|null)
     * - Array shapes (array<string, int>)
     * - Generic types
     * 
     * @param string $type Raw type string from PHPDoc
     * @return string Normalized type string
     */
    private function normalizeType(string $type): string
    {
        $type = trim($type);

        // Handle empty type
        if ($type === '') {
            return 'mixed';
        }

        // Handle void
        if (strtolower($type) === 'void') {
            return 'void';
        }

        // Handle mixed
        if (strtolower($type) === 'mixed') {
            return 'mixed';
        }

        // Handle null
        if (strtolower($type) === 'null') {
            return 'null';
        }

        // Handle boolean variations
        if (in_array(strtolower($type), ['bool', 'boolean'], true)) {
            return 'bool';
        }

        // Handle integer variations
        if (in_array(strtolower($type), ['int', 'integer'], true)) {
            return 'int';
        }

        // Handle float variations
        if (in_array(strtolower($type), ['float', 'double'], true)) {
            return 'float';
        }

        // Handle string
        if (strtolower($type) === 'string') {
            return 'string';
        }

        // Handle array with generic notation (array<T> or array<K, V>)
        if (preg_match('/^array\s*<[^>]+>$/i', $type)) {
            // For now, simplify to just 'array' as PHP doesn't support generic syntax
            return 'array';
        }

        // Handle array notation variations (int[], string[], etc.)
        if (preg_match('/^(.+)\[\]$/', $type, $matches)) {
            return 'array';
        }

        // Handle union types (string|int|null)
        if (str_contains($type, '|')) {
            return $this->normalizeUnionType($type);
        }

        // Handle nullable shorthand (?string)
        if (str_starts_with($type, '?')) {
            $baseType = $this->normalizeType(substr($type, 1));
            return $baseType;
        }

        // Handle class names and other types as-is
        // Remove leading backslash if present
        if (str_starts_with($type, '\\')) {
            $type = substr($type, 1);
        }

        return $type;
    }

    /**
     * Normalize union type string
     * 
     * @param string $type Union type string (e.g., "string|int|null")
     * @return string Normalized union type
     */
    private function normalizeUnionType(string $type): string
    {
        $parts = explode('|', $type);
        $normalizedParts = [];
        $hasNull = false;

        foreach ($parts as $part) {
            $part = trim($part);
            $normalized = $this->normalizeType($part);
            
            if (strtolower($normalized) === 'null') {
                $hasNull = true;
            } else {
                $normalizedParts[] = $normalized;
            }
        }

        // Remove duplicates and sort for consistency
        $normalizedParts = array_unique($normalizedParts);
        sort($normalizedParts);

        // Add null at the end if present
        if ($hasNull) {
            $normalizedParts[] = 'null';
        }

        // If only null remains, return null
        if (empty($normalizedParts)) {
            return 'null';
        }

        // If we have multiple types, return union
        if (count($normalizedParts) > 1) {
            return implode('|', $normalizedParts);
        }

        // Single type
        return $normalizedParts[0];
    }

    /**
     * Check if a type is nullable
     * 
     * @param string $type Type string
     * @return bool True if the type is nullable
     */
    public function isNullable(string $type): bool
    {
        // Check for nullable shorthand
        if (str_starts_with($type, '?')) {
            return true;
        }

        // Check for null in union type
        if (str_contains($type, '|')) {
            $parts = explode('|', $type);
            foreach ($parts as $part) {
                if (strtolower(trim($part)) === 'null') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Remove null from a nullable type
     * 
     * @param string $type Type string
     * @return string Type without null
     */
    public function removeNullFromType(string $type): string
    {
        // Handle nullable shorthand
        if (str_starts_with($type, '?')) {
            return substr($type, 1);
        }

        // Handle union types
        if (str_contains($type, '|')) {
            $parts = explode('|', $type);
            $nonNullParts = [];
            
            foreach ($parts as $part) {
                $part = trim($part);
                if (strtolower($part) !== 'null') {
                    $nonNullParts[] = $part;
                }
            }

            if (empty($nonNullParts)) {
                return 'mixed';
            }

            if (count($nonNullParts) === 1) {
                return $nonNullParts[0];
            }

            return implode('|', $nonNullParts);
        }

        // If it's just 'null', return mixed
        if (strtolower($type) === 'null') {
            return 'mixed';
        }

        return $type;
    }
}
