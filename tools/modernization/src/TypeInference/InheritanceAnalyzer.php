<?php

declare(strict_types=1);

namespace Gibbon\Modernization\TypeInference;

use PhpParser\Node;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionMethod;
use ReflectionException;

/**
 * InheritanceAnalyzer - Analyzes inheritance hierarchies for type information
 * 
 * Looks up parent class and interface method signatures to infer types.
 * Checks covariance and contravariance rules for type compatibility.
 * Handles @inheritDoc annotations.
 * 
 * Requirements: 6.2, 6.3, 6.4, 6.5, 11.5
 */
class InheritanceAnalyzer
{
    private PHPDocAnalyzer $phpDocAnalyzer;
    private NodeFinder $nodeFinder;
    
    /** @var array<string, Node\Stmt\Class_> Cache of parsed class nodes */
    private array $classCache = [];
    
    /** @var array<string, Node\Stmt\Interface_> Cache of parsed interface nodes */
    private array $interfaceCache = [];

    public function __construct(?PHPDocAnalyzer $phpDocAnalyzer = null)
    {
        $this->phpDocAnalyzer = $phpDocAnalyzer ?? new PHPDocAnalyzer();
        $this->nodeFinder = new NodeFinder();
    }

    /**
     * Find parent class method signature
     * 
     * @param string $className Fully qualified class name
     * @param string $methodName Method name
     * @return MethodSignature|null Method signature or null if not found
     */
    public function findParentMethodSignature(string $className, string $methodName): ?MethodSignature
    {
        try {
            $reflection = new ReflectionClass($className);
            $parent = $reflection->getParentClass();
            
            if ($parent === false) {
                return null;
            }
            
            if (!$parent->hasMethod($methodName)) {
                return null;
            }
            
            $method = $parent->getMethod($methodName);
            return $this->extractMethodSignatureFromReflection($method);
        } catch (ReflectionException $e) {
            // Class not found or not loaded, try AST-based lookup
            return $this->findParentMethodSignatureFromAST($className, $methodName);
        }
    }

    /**
     * Find interface method signature
     * 
     * @param string $className Fully qualified class name
     * @param string $methodName Method name
     * @return MethodSignature|null Method signature or null if not found
     */
    public function findInterfaceMethodSignature(string $className, string $methodName): ?MethodSignature
    {
        try {
            $reflection = new ReflectionClass($className);
            $interfaces = $reflection->getInterfaces();
            
            foreach ($interfaces as $interface) {
                if ($interface->hasMethod($methodName)) {
                    $method = $interface->getMethod($methodName);
                    return $this->extractMethodSignatureFromReflection($method);
                }
            }
            
            return null;
        } catch (ReflectionException $e) {
            // Class not found or not loaded, try AST-based lookup
            return $this->findInterfaceMethodSignatureFromAST($className, $methodName);
        }
    }

    /**
     * Check if child method signature is compatible with parent (covariance/contravariance)
     * 
     * PHP type variance rules:
     * - Return types are covariant (child can return more specific type)
     * - Parameter types are contravariant (child can accept more general type)
     * 
     * @param MethodSignature $parent Parent method signature
     * @param MethodSignature $child Child method signature
     * @return bool True if compatible
     */
    public function checkCovariance(MethodSignature $parent, MethodSignature $child): bool
    {
        // Check parameter count compatibility
        if (count($child->parameters) < count($parent->parameters)) {
            // Child has fewer required parameters - not compatible
            return false;
        }

        // Check parameter type compatibility (contravariance)
        foreach ($parent->parameters as $index => $parentParam) {
            if (!isset($child->parameters[$index])) {
                continue;
            }
            
            $childParam = $child->parameters[$index];
            
            // If parent has no type, child can have any type
            if ($parentParam['type'] === null) {
                continue;
            }
            
            // If parent has type, child must have compatible type
            if ($childParam['type'] === null) {
                // Child has no type but parent does - not compatible
                return false;
            }
            
            // Check contravariance: child type must be same or more general
            if (!$this->isTypeCompatible($parentParam['type'], $childParam['type'], false)) {
                return false;
            }
        }

        // Check return type compatibility (covariance)
        if ($parent->returnType !== null && $child->returnType !== null) {
            // Check covariance: child type must be same or more specific
            if (!$this->isTypeCompatible($parent->returnType, $child->returnType, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a method has @inheritDoc annotation
     * 
     * @param Node\Stmt\ClassMethod $method Method node
     * @return bool True if has @inheritDoc
     */
    public function hasInheritDocAnnotation(Node\Stmt\ClassMethod $method): bool
    {
        $phpDoc = $this->phpDocAnalyzer->extractPhpDocFromNode($method);
        
        if ($phpDoc === null) {
            return false;
        }

        return str_contains($phpDoc, '@inheritDoc') || str_contains($phpDoc, '@inheritdoc');
    }

    /**
     * Infer types from parent method when @inheritDoc is present
     * 
     * @param string $className Fully qualified class name
     * @param string $methodName Method name
     * @return MethodSignature|null Parent method signature or null
     */
    public function inferFromInheritDoc(string $className, string $methodName): ?MethodSignature
    {
        // Try parent class first
        $signature = $this->findParentMethodSignature($className, $methodName);
        
        if ($signature !== null) {
            return $signature;
        }

        // Try interfaces
        return $this->findInterfaceMethodSignature($className, $methodName);
    }

    /**
     * Register a parsed class node for AST-based lookups
     * 
     * @param string $className Fully qualified class name
     * @param Node\Stmt\Class_ $classNode Class node
     * @return void
     */
    public function registerClass(string $className, Node\Stmt\Class_ $classNode): void
    {
        $this->classCache[$className] = $classNode;
    }

    /**
     * Register a parsed interface node for AST-based lookups
     * 
     * @param string $interfaceName Fully qualified interface name
     * @param Node\Stmt\Interface_ $interfaceNode Interface node
     * @return void
     */
    public function registerInterface(string $interfaceName, Node\Stmt\Interface_ $interfaceNode): void
    {
        $this->interfaceCache[$interfaceName] = $interfaceNode;
    }

    /**
     * Extract method signature from reflection
     * 
     * @param ReflectionMethod $method Reflection method
     * @return MethodSignature Method signature
     */
    private function extractMethodSignatureFromReflection(ReflectionMethod $method): MethodSignature
    {
        $parameters = [];
        
        foreach ($method->getParameters() as $param) {
            $type = null;
            if ($param->hasType()) {
                $reflectionType = $param->getType();
                $type = $this->reflectionTypeToString($reflectionType);
            }
            
            $parameters[] = [
                'name' => $param->getName(),
                'type' => $type,
                'isNullable' => $param->allowsNull(),
                'hasDefault' => $param->isDefaultValueAvailable(),
            ];
        }

        $returnType = null;
        if ($method->hasReturnType()) {
            $reflectionType = $method->getReturnType();
            $returnType = $this->reflectionTypeToString($reflectionType);
        }

        return new MethodSignature(
            $method->getName(),
            $parameters,
            $returnType
        );
    }

    /**
     * Convert reflection type to string
     * 
     * @param \ReflectionType $type Reflection type
     * @return string Type string
     */
    private function reflectionTypeToString(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            $name = $type->getName();
            return $type->allowsNull() && $name !== 'null' && $name !== 'mixed' ? '?' . $name : $name;
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = array_map(fn($t) => $t->getName(), $type->getTypes());
            return implode('|', $types);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            $types = array_map(fn($t) => $t->getName(), $type->getTypes());
            return implode('&', $types);
        }

        return 'mixed';
    }

    /**
     * Find parent method signature from AST
     * 
     * @param string $className Fully qualified class name
     * @param string $methodName Method name
     * @return MethodSignature|null Method signature or null
     */
    private function findParentMethodSignatureFromAST(string $className, string $methodName): ?MethodSignature
    {
        if (!isset($this->classCache[$className])) {
            return null;
        }

        $classNode = $this->classCache[$className];
        
        if ($classNode->extends === null) {
            return null;
        }

        $parentClassName = $classNode->extends->toString();
        
        if (!isset($this->classCache[$parentClassName])) {
            return null;
        }

        return $this->findMethodInClass($this->classCache[$parentClassName], $methodName);
    }

    /**
     * Find interface method signature from AST
     * 
     * @param string $className Fully qualified class name
     * @param string $methodName Method name
     * @return MethodSignature|null Method signature or null
     */
    private function findInterfaceMethodSignatureFromAST(string $className, string $methodName): ?MethodSignature
    {
        if (!isset($this->classCache[$className])) {
            return null;
        }

        $classNode = $this->classCache[$className];
        
        foreach ($classNode->implements as $interface) {
            $interfaceName = $interface->toString();
            
            if (isset($this->interfaceCache[$interfaceName])) {
                $signature = $this->findMethodInInterface($this->interfaceCache[$interfaceName], $methodName);
                if ($signature !== null) {
                    return $signature;
                }
            }
        }

        return null;
    }

    /**
     * Find method in class node
     * 
     * @param Node\Stmt\Class_ $classNode Class node
     * @param string $methodName Method name
     * @return MethodSignature|null Method signature or null
     */
    private function findMethodInClass(Node\Stmt\Class_ $classNode, string $methodName): ?MethodSignature
    {
        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->toString() === $methodName) {
                return $this->extractMethodSignatureFromNode($stmt);
            }
        }

        return null;
    }

    /**
     * Find method in interface node
     * 
     * @param Node\Stmt\Interface_ $interfaceNode Interface node
     * @param string $methodName Method name
     * @return MethodSignature|null Method signature or null
     */
    private function findMethodInInterface(Node\Stmt\Interface_ $interfaceNode, string $methodName): ?MethodSignature
    {
        foreach ($interfaceNode->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->toString() === $methodName) {
                return $this->extractMethodSignatureFromNode($stmt);
            }
        }

        return null;
    }

    /**
     * Extract method signature from AST node
     * 
     * @param Node\Stmt\ClassMethod $method Method node
     * @return MethodSignature Method signature
     */
    private function extractMethodSignatureFromNode(Node\Stmt\ClassMethod $method): MethodSignature
    {
        $parameters = [];
        
        foreach ($method->params as $param) {
            $type = null;
            if ($param->type !== null) {
                $type = $this->nodeTypeToString($param->type);
            }
            
            $parameters[] = [
                'name' => $param->var->name,
                'type' => $type,
                'isNullable' => $param->type instanceof Node\NullableType || 
                               ($param->type instanceof Node\UnionType && $this->hasNullInUnion($param->type)),
                'hasDefault' => $param->default !== null,
            ];
        }

        $returnType = null;
        if ($method->returnType !== null) {
            $returnType = $this->nodeTypeToString($method->returnType);
        }

        return new MethodSignature(
            $method->name->toString(),
            $parameters,
            $returnType
        );
    }

    /**
     * Convert node type to string
     * 
     * @param Node\Identifier|Node\Name|Node\NullableType|Node\UnionType|Node\IntersectionType $type Type node
     * @return string Type string
     */
    private function nodeTypeToString($type): string
    {
        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }

        if ($type instanceof Node\Name) {
            return $type->toString();
        }

        if ($type instanceof Node\NullableType) {
            return '?' . $this->nodeTypeToString($type->type);
        }

        if ($type instanceof Node\UnionType) {
            $types = array_map(fn($t) => $this->nodeTypeToString($t), $type->types);
            return implode('|', $types);
        }

        if ($type instanceof Node\IntersectionType) {
            $types = array_map(fn($t) => $this->nodeTypeToString($t), $type->types);
            return implode('&', $types);
        }

        return 'mixed';
    }

    /**
     * Check if union type contains null
     * 
     * @param Node\UnionType $unionType Union type node
     * @return bool True if contains null
     */
    private function hasNullInUnion(Node\UnionType $unionType): bool
    {
        foreach ($unionType->types as $type) {
            if ($type instanceof Node\Identifier && strtolower($type->toString()) === 'null') {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if two types are compatible
     * 
     * @param string $parentType Parent type
     * @param string $childType Child type
     * @param bool $covariant True for covariance (return types), false for contravariance (parameters)
     * @return bool True if compatible
     */
    private function isTypeCompatible(string $parentType, string $childType, bool $covariant): bool
    {
        // Exact match is always compatible
        if ($parentType === $childType) {
            return true;
        }

        // mixed is compatible with everything
        if ($childType === 'mixed' && !$covariant) {
            return true; // Contravariant: child can be more general
        }

        if ($parentType === 'mixed' && $covariant) {
            return true; // Covariant: child can be more specific
        }

        // Handle nullable types
        $parentNullable = str_starts_with($parentType, '?');
        $childNullable = str_starts_with($childType, '?');

        if ($parentNullable) {
            $parentType = substr($parentType, 1);
        }
        if ($childNullable) {
            $childType = substr($childType, 1);
        }

        // For covariance (return types): child can be more specific
        // For contravariance (parameters): child can be more general
        
        // Simplified compatibility check
        // In a real implementation, this would need to check class hierarchies
        
        return $parentType === $childType;
    }
}

