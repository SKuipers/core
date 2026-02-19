<?php

declare(strict_types=1);

namespace Gibbon\Modernization\TypeInference;

use PhpParser\Node;

/**
 * TypeInferenceEngine - Orchestrates type inference from multiple sources
 * 
 * Intelligently infers types using a priority-based approach:
 * 1. PHPDoc annotations (highest priority - most explicit)
 * 2. Inheritance from parent classes/interfaces
 * 3. Usage analysis from code patterns (lowest priority)
 * 
 * Returns InferredType with confidence score and source information.
 * 
 * Requirements: 2.1, 2.2, 3.1, 3.2, 3.3, 4.1, 4.2, 5.1, 5.2
 */
class TypeInferenceEngine
{
    private const CONFIDENCE_PHPDOC = 0.95;
    private const CONFIDENCE_INHERITANCE = 0.90;
    private const CONFIDENCE_USAGE_HIGH = 0.80;
    private const CONFIDENCE_USAGE_MEDIUM = 0.60;
    private const CONFIDENCE_FALLBACK = 0.30;

    public function __construct(
        private PHPDocAnalyzer $phpDocAnalyzer,
        private UsageAnalyzer $usageAnalyzer,
        private InheritanceAnalyzer $inheritanceAnalyzer
    ) {}

    /**
     * Infer parameter type using priority logic
     * 
     * Priority order:
     * 1. PHPDoc @param annotation
     * 2. Inheritance from parent/interface method
     * 3. Usage analysis within method body
     * 4. Fallback to 'mixed'
     * 
     * @param Node\Param $param Parameter node
     * @param Node\Stmt\ClassMethod|Node\Stmt\Function_ $method Method or function node
     * @param string|null $className Fully qualified class name (for inheritance lookup)
     * @return InferredType Inferred type with confidence and source
     */
    public function inferParameterType(
        Node\Param $param,
        $method,
        ?string $className = null
    ): InferredType {
        $paramName = $param->var->name;
        $hasDefaultNull = $param->default instanceof Node\Expr\ConstFetch &&
                         strtolower($param->default->name->toString()) === 'null';

        // Priority 1: PHPDoc annotation
        $phpDoc = $this->phpDocAnalyzer->extractPhpDocFromNode($method);
        if ($phpDoc !== null) {
            $phpDocType = $this->phpDocAnalyzer->extractParameterType($phpDoc, $paramName);
            if ($phpDocType !== null && $phpDocType !== '') {
                $isNullable = $this->phpDocAnalyzer->isNullable($phpDocType) || $hasDefaultNull;
                $cleanType = $this->phpDocAnalyzer->removeNullFromType($phpDocType);
                
                return new InferredType(
                    $cleanType,
                    $isNullable,
                    self::CONFIDENCE_PHPDOC,
                    'phpdoc'
                );
            }
        }

        // Priority 2: Inheritance
        if ($className !== null && $method instanceof Node\Stmt\ClassMethod) {
            $methodName = $method->name->toString();
            
            // Check parent class
            $parentSignature = $this->inheritanceAnalyzer->findParentMethodSignature(
                $className,
                $methodName
            );
            
            if ($parentSignature !== null) {
                $paramType = $parentSignature->getParameterType($paramName);
                if ($paramType !== null) {
                    $isNullable = $parentSignature->isParameterNullable($paramName) || $hasDefaultNull;
                    
                    return new InferredType(
                        $this->cleanTypeString($paramType),
                        $isNullable,
                        self::CONFIDENCE_INHERITANCE,
                        'inheritance'
                    );
                }
            }
            
            // Check interface
            $interfaceSignature = $this->inheritanceAnalyzer->findInterfaceMethodSignature(
                $className,
                $methodName
            );
            
            if ($interfaceSignature !== null) {
                $paramType = $interfaceSignature->getParameterType($paramName);
                if ($paramType !== null) {
                    $isNullable = $interfaceSignature->isParameterNullable($paramName) || $hasDefaultNull;
                    
                    return new InferredType(
                        $this->cleanTypeString($paramType),
                        $isNullable,
                        self::CONFIDENCE_INHERITANCE,
                        'inheritance'
                    );
                }
            }
        }

        // Priority 3: Usage analysis
        $usageInfo = $this->usageAnalyzer->analyzeParameterUsage($paramName, $method);
        if ($usageInfo->isConfident(0.5)) {
            $confidence = $usageInfo->confidence >= 0.8 
                ? self::CONFIDENCE_USAGE_HIGH 
                : self::CONFIDENCE_USAGE_MEDIUM;
            
            return new InferredType(
                $usageInfo->inferredType,
                $hasDefaultNull,
                $confidence,
                'usage'
            );
        }

        // Fallback: mixed type
        return new InferredType(
            'mixed',
            $hasDefaultNull,
            self::CONFIDENCE_FALLBACK,
            'fallback'
        );
    }

    /**
     * Infer return type using priority logic
     * 
     * Priority order:
     * 1. PHPDoc @return annotation
     * 2. Inheritance from parent/interface method
     * 3. Usage analysis of return statements
     * 4. Fallback to 'mixed'
     * 
     * @param Node\Stmt\ClassMethod|Node\Stmt\Function_ $method Method or function node
     * @param string|null $className Fully qualified class name (for inheritance lookup)
     * @return InferredType Inferred type with confidence and source
     */
    public function inferReturnType(
        $method,
        ?string $className = null
    ): InferredType {
        // Priority 1: PHPDoc annotation
        $phpDoc = $this->phpDocAnalyzer->extractPhpDocFromNode($method);
        if ($phpDoc !== null) {
            $phpDocType = $this->phpDocAnalyzer->extractReturnType($phpDoc);
            if ($phpDocType !== null && $phpDocType !== '') {
                // Check for nullable BEFORE normalization removed it
                // The extractReturnType already normalizes, but we need to check the raw PHPDoc
                $isNullable = str_contains($phpDoc, '@return ?') || 
                             str_contains($phpDoc, '@return null') ||
                             $this->phpDocAnalyzer->isNullable($phpDocType);
                $cleanType = $this->phpDocAnalyzer->removeNullFromType($phpDocType);
                
                return new InferredType(
                    $cleanType,
                    $isNullable,
                    self::CONFIDENCE_PHPDOC,
                    'phpdoc'
                );
            }
        }

        // Priority 2: Inheritance
        if ($className !== null && $method instanceof Node\Stmt\ClassMethod) {
            $methodName = $method->name->toString();
            
            // Check parent class
            $parentSignature = $this->inheritanceAnalyzer->findParentMethodSignature(
                $className,
                $methodName
            );
            
            if ($parentSignature !== null && $parentSignature->returnType !== null) {
                $isNullable = str_starts_with($parentSignature->returnType, '?') ||
                             str_contains($parentSignature->returnType, '|null');
                
                return new InferredType(
                    $this->cleanTypeString($parentSignature->returnType),
                    $isNullable,
                    self::CONFIDENCE_INHERITANCE,
                    'inheritance'
                );
            }
            
            // Check interface
            $interfaceSignature = $this->inheritanceAnalyzer->findInterfaceMethodSignature(
                $className,
                $methodName
            );
            
            if ($interfaceSignature !== null && $interfaceSignature->returnType !== null) {
                $isNullable = str_starts_with($interfaceSignature->returnType, '?') ||
                             str_contains($interfaceSignature->returnType, '|null');
                
                return new InferredType(
                    $this->cleanTypeString($interfaceSignature->returnType),
                    $isNullable,
                    self::CONFIDENCE_INHERITANCE,
                    'inheritance'
                );
            }
        }

        // Priority 3: Usage analysis
        $returnTypes = $this->usageAnalyzer->analyzeReturnStatements($method);
        
        if (!empty($returnTypes)) {
            $inferredType = $this->mergeReturnTypes($returnTypes);
            $isNullable = in_array('null', $returnTypes, true);
            $confidence = count($returnTypes) === 1 
                ? self::CONFIDENCE_USAGE_HIGH 
                : self::CONFIDENCE_USAGE_MEDIUM;
            
            return new InferredType(
                $inferredType,
                $isNullable,
                $confidence,
                'usage'
            );
        }

        // Fallback: mixed type
        return new InferredType(
            'mixed',
            false,
            self::CONFIDENCE_FALLBACK,
            'fallback'
        );
    }

    /**
     * Infer property type using priority logic
     * 
     * Priority order:
     * 1. PHPDoc @var annotation
     * 2. Usage analysis of property assignments
     * 3. Fallback to 'mixed'
     * 
     * @param Node\Stmt\Property $property Property node
     * @param Node\Stmt\Class_ $class Class node
     * @return InferredType Inferred type with confidence and source
     */
    public function inferPropertyType(
        Node\Stmt\Property $property,
        Node\Stmt\Class_ $class
    ): InferredType {
        $propertyName = $property->props[0]->name->toString();
        $hasDefaultNull = $property->props[0]->default instanceof Node\Expr\ConstFetch &&
                         strtolower($property->props[0]->default->name->toString()) === 'null';

        // Priority 1: PHPDoc annotation
        $phpDoc = $this->phpDocAnalyzer->extractPhpDocFromNode($property);
        if ($phpDoc !== null) {
            $phpDocType = $this->phpDocAnalyzer->extractPropertyType($phpDoc);
            if ($phpDocType !== null && $phpDocType !== '') {
                $isNullable = $this->phpDocAnalyzer->isNullable($phpDocType) || $hasDefaultNull;
                $cleanType = $this->phpDocAnalyzer->removeNullFromType($phpDocType);
                
                return new InferredType(
                    $cleanType,
                    $isNullable,
                    self::CONFIDENCE_PHPDOC,
                    'phpdoc'
                );
            }
        }

        // Priority 2: Usage analysis
        $assignmentTypes = $this->usageAnalyzer->analyzePropertyAssignments($propertyName, $class);
        
        if (!empty($assignmentTypes)) {
            $inferredType = $this->mergePropertyTypes($assignmentTypes);
            $isNullable = in_array('null', $assignmentTypes, true) || $hasDefaultNull;
            $confidence = count($assignmentTypes) === 1 
                ? self::CONFIDENCE_USAGE_HIGH 
                : self::CONFIDENCE_USAGE_MEDIUM;
            
            return new InferredType(
                $inferredType,
                $isNullable,
                $confidence,
                'usage'
            );
        }

        // Fallback: mixed type
        return new InferredType(
            'mixed',
            $hasDefaultNull,
            self::CONFIDENCE_FALLBACK,
            'fallback'
        );
    }

    /**
     * Clean type string by removing nullable prefix
     * 
     * @param string $type Type string
     * @return string Cleaned type string
     */
    private function cleanTypeString(string $type): string
    {
        if (str_starts_with($type, '?')) {
            return substr($type, 1);
        }
        
        // Remove null from union types
        if (str_contains($type, '|null')) {
            $type = str_replace('|null', '', $type);
        }
        if (str_contains($type, 'null|')) {
            $type = str_replace('null|', '', $type);
        }
        
        return $type;
    }

    /**
     * Merge multiple return types into a single type
     * 
     * @param array<string> $types Array of return types
     * @return string Merged type
     */
    private function mergeReturnTypes(array $types): string
    {
        // Remove null from types for merging
        $nonNullTypes = array_filter($types, fn($t) => strtolower($t) !== 'null' && strtolower($t) !== 'void');
        
        if (empty($nonNullTypes)) {
            return 'void';
        }
        
        // Remove duplicates
        $uniqueTypes = array_unique($nonNullTypes);
        
        // If only one type, return it
        if (count($uniqueTypes) === 1) {
            return reset($uniqueTypes);
        }
        
        // Multiple types - create union
        sort($uniqueTypes);
        return implode('|', $uniqueTypes);
    }

    /**
     * Merge multiple property assignment types into a single type
     * 
     * @param array<string> $types Array of assignment types
     * @return string Merged type
     */
    private function mergePropertyTypes(array $types): string
    {
        // Remove null from types for merging
        $nonNullTypes = array_filter($types, fn($t) => strtolower($t) !== 'null');
        
        if (empty($nonNullTypes)) {
            return 'mixed';
        }
        
        // Remove duplicates
        $uniqueTypes = array_unique($nonNullTypes);
        
        // If only one type, return it
        if (count($uniqueTypes) === 1) {
            return reset($uniqueTypes);
        }
        
        // Multiple types - create union
        sort($uniqueTypes);
        return implode('|', $uniqueTypes);
    }
}
