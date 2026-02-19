<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Scanner;

use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * FileAnalyzer - Analyzes PHP files for deprecated patterns and missing type hints
 * 
 * Identifies implicitly nullable parameters, missing parameter type hints,
 * missing return type hints, and missing property type hints.
 * 
 * Requirements: 1.1, 1.2
 */
class FileAnalyzer
{
    public function __construct(
        private readonly ASTParser $astParser
    ) {}

    /**
     * Analyze a PHP file for deprecated patterns and missing type hints
     * 
     * @param string $filePath Path to the PHP file to analyze
     * @return FileAnalysis Analysis results containing all identified issues
     */
    public function analyze(string $filePath): FileAnalysis
    {
        $parseResult = $this->astParser->parseFile($filePath);
        
        if ($parseResult->isError()) {
            return FileAnalysis::error(
                $filePath,
                $parseResult->getErrorMessage(),
                $parseResult->getErrorCode()
            );
        }

        $ast = $parseResult->getAst();
        
        return FileAnalysis::success(
            $filePath,
            $this->findImplicitlyNullableParameters($ast),
            $this->findMissingTypeHints($ast)
        );
    }

    /**
     * Find all implicitly nullable parameters in the AST
     * 
     * Identifies parameters with default value null but without explicit nullable type hints.
     * 
     * @param array<Node> $ast The Abstract Syntax Tree to analyze
     * @return array<array{file: string, line: int, function: string, parameter: string}>
     */
    public function findImplicitlyNullableParameters(array $ast): array
    {
        $issues = [];
        
        $traverser = new NodeTraverser();
        $visitor = new class($issues) extends NodeVisitorAbstract {
            private array $issues;
            private ?string $currentFunction = null;
            
            public function __construct(array &$issues)
            {
                $this->issues = &$issues;
            }
            
            public function enterNode(Node $node)
            {
                // Track current function/method name
                if ($node instanceof Function_) {
                    $this->currentFunction = $node->name->toString();
                    $this->checkParameters($node->params, $node->getLine());
                } elseif ($node instanceof ClassMethod) {
                    $this->currentFunction = $node->name->toString();
                    $this->checkParameters($node->params, $node->getLine());
                }
                
                return null;
            }
            
            public function leaveNode(Node $node)
            {
                if ($node instanceof Function_ || $node instanceof ClassMethod) {
                    $this->currentFunction = null;
                }
                
                return null;
            }
            
            private function checkParameters(array $params, int $line): void
            {
                foreach ($params as $param) {
                    if ($this->isImplicitlyNullable($param)) {
                        $this->issues[] = [
                            'line' => $param->getLine(),
                            'function' => $this->currentFunction ?? 'unknown',
                            'parameter' => $param->var->name,
                        ];
                    }
                }
            }
            
            private function isImplicitlyNullable(Param $param): bool
            {
                // Check if parameter has default value of null
                if ($param->default === null) {
                    return false;
                }
                
                // Check if default value is null constant
                $isDefaultNull = $param->default instanceof Node\Expr\ConstFetch 
                    && $param->default->name->toLowerString() === 'null';
                
                if (!$isDefaultNull) {
                    return false;
                }
                
                // Check if parameter already has a type hint
                if ($param->type === null) {
                    return true; // No type hint at all
                }
                
                // Check if type hint is already nullable
                if ($param->type instanceof Node\NullableType) {
                    return false; // Already explicitly nullable
                }
                
                // Check if type hint is a union type containing null
                if ($param->type instanceof Node\UnionType) {
                    foreach ($param->type->types as $type) {
                        if ($type instanceof Node\Identifier && $type->toLowerString() === 'null') {
                            return false; // Already explicitly nullable via union
                        }
                    }
                }
                
                // Has a type hint but not nullable - this is implicitly nullable
                return true;
            }
            
            public function getIssues(): array
            {
                return $this->issues;
            }
        };
        
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
        
        return $visitor->getIssues();
    }

    /**
     * Find all missing type hints in the AST
     * 
     * Identifies missing parameter type hints, return type hints, and property type hints.
     * 
     * @param array<Node> $ast The Abstract Syntax Tree to analyze
     * @return array{parameters: array, returns: array, properties: array}
     */
    public function findMissingTypeHints(array $ast): array
    {
        $missingParameters = [];
        $missingReturns = [];
        $missingProperties = [];
        
        $traverser = new NodeTraverser();
        $visitor = new class($missingParameters, $missingReturns, $missingProperties) extends NodeVisitorAbstract {
            private array $missingParameters;
            private array $missingReturns;
            private array $missingProperties;
            private ?string $currentFunction = null;
            private ?string $currentClass = null;
            
            public function __construct(
                array &$missingParameters,
                array &$missingReturns,
                array &$missingProperties
            ) {
                $this->missingParameters = &$missingParameters;
                $this->missingReturns = &$missingReturns;
                $this->missingProperties = &$missingProperties;
            }
            
            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->currentClass = $node->name ? $node->name->toString() : 'anonymous';
                } elseif ($node instanceof Function_) {
                    $this->currentFunction = $node->name->toString();
                    $this->checkFunctionTypeHints($node);
                } elseif ($node instanceof ClassMethod) {
                    $this->currentFunction = $node->name->toString();
                    $this->checkMethodTypeHints($node);
                } elseif ($node instanceof Property) {
                    $this->checkPropertyTypeHints($node);
                }
                
                return null;
            }
            
            public function leaveNode(Node $node)
            {
                if ($node instanceof Function_ || $node instanceof ClassMethod) {
                    $this->currentFunction = null;
                } elseif ($node instanceof Node\Stmt\Class_) {
                    $this->currentClass = null;
                }
                
                return null;
            }
            
            private function checkFunctionTypeHints(Function_ $function): void
            {
                // Check parameters
                foreach ($function->params as $param) {
                    if ($param->type === null) {
                        $this->missingParameters[] = [
                            'line' => $param->getLine(),
                            'function' => $function->name->toString(),
                            'parameter' => $param->var->name,
                        ];
                    }
                }
                
                // Check return type
                if ($function->returnType === null) {
                    $this->missingReturns[] = [
                        'line' => $function->getLine(),
                        'function' => $function->name->toString(),
                    ];
                }
            }
            
            private function checkMethodTypeHints(ClassMethod $method): void
            {
                // Check parameters
                foreach ($method->params as $param) {
                    if ($param->type === null) {
                        $this->missingParameters[] = [
                            'line' => $param->getLine(),
                            'class' => $this->currentClass ?? 'unknown',
                            'method' => $method->name->toString(),
                            'parameter' => $param->var->name,
                        ];
                    }
                }
                
                // Check return type
                if ($method->returnType === null) {
                    $this->missingReturns[] = [
                        'line' => $method->getLine(),
                        'class' => $this->currentClass ?? 'unknown',
                        'method' => $method->name->toString(),
                    ];
                }
            }
            
            private function checkPropertyTypeHints(Property $property): void
            {
                if ($property->type === null) {
                    foreach ($property->props as $prop) {
                        $this->missingProperties[] = [
                            'line' => $prop->getLine(),
                            'class' => $this->currentClass ?? 'unknown',
                            'property' => $prop->name->toString(),
                        ];
                    }
                }
            }
            
            public function getMissingParameters(): array
            {
                return $this->missingParameters;
            }
            
            public function getMissingReturns(): array
            {
                return $this->missingReturns;
            }
            
            public function getMissingProperties(): array
            {
                return $this->missingProperties;
            }
        };
        
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
        
        return [
            'parameters' => $visitor->getMissingParameters(),
            'returns' => $visitor->getMissingReturns(),
            'properties' => $visitor->getMissingProperties(),
        ];
    }
}
