<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Migration;

use Gibbon\Modernization\Scanner\ASTParser;
use Gibbon\Modernization\TypeInference\InferredType;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * CodeTransformerV2 - Applies type hint transformations while preserving formatting
 * 
 * Uses a hybrid approach: AST for finding locations, regex for surgical replacements
 * to preserve original whitespace and formatting.
 */
class CodeTransformerV2
{
    private ASTParser $parser;

    public function __construct(?ASTParser $parser = null)
    {
        $this->parser = $parser ?? new ASTParser();
    }

    /**
     * Add type hint to a parameter
     */
    public function addParameterType(
        string $code,
        string $paramName,
        InferredType $type,
        ?string $methodName = null
    ): string {
        $parseResult = $this->parser->parseCode($code);
        
        if (!$parseResult->isSuccess()) {
            return $code;
        }

        // Find the parameter location using AST
        $locations = $this->findParameterLocations($parseResult->getAst(), $paramName, $methodName);
        
        if (empty($locations)) {
            return $code;
        }

        // Apply replacements from bottom to top to preserve line numbers
        $lines = explode("\n", $code);
        
        foreach (array_reverse($locations) as $location) {
            $lineIndex = $location['line'] - 1;
            if (!isset($lines[$lineIndex])) {
                continue;
            }
            
            $line = $lines[$lineIndex];
            $typeString = $type->toString();
            
            // Pattern to match parameter without type hint
            // Matches: $paramName, &$paramName, ...$paramName
            $pattern = '/(\(|,)\s*(&|\.\.\.)?\s*(\$' . preg_quote($paramName, '/') . ')\b/';
            
            $replacement = '$1 ' . ($location['isReference'] ? '&' : '') . 
                          ($location['isVariadic'] ? '...' : '') . 
                          $typeString . ' $3';
            
            $lines[$lineIndex] = preg_replace($pattern, $replacement, $line, 1);
        }
        
        return implode("\n", $lines);
    }

    /**
     * Add return type hint to a method or function
     */
    public function addReturnType(
        string $code,
        string $methodName,
        InferredType $type
    ): string {
        $parseResult = $this->parser->parseCode($code);
        
        if (!$parseResult->isSuccess()) {
            return $code;
        }

        // Find the method/function location
        $location = $this->findMethodLocation($parseResult->getAst(), $methodName);
        
        if ($location === null) {
            return $code;
        }

        $lines = explode("\n", $code);
        $lineIndex = $location['line'] - 1;
        
        if (!isset($lines[$lineIndex])) {
            return $code;
        }

        $typeString = $type->toString();
        
        // Pattern to match closing parenthesis of parameter list
        // We need to add ": type" after the closing paren and before the opening brace or semicolon
        $pattern = '/(\))\s*(\{|;)/';
        $replacement = '$1: ' . $typeString . ' $2';
        
        $lines[$lineIndex] = preg_replace($pattern, $replacement, $lines[$lineIndex], 1);
        
        return implode("\n", $lines);
    }

    /**
     * Add type hint to a property
     */
    public function addPropertyType(
        string $code,
        string $propertyName,
        InferredType $type
    ): string {
        $parseResult = $this->parser->parseCode($code);
        
        if (!$parseResult->isSuccess()) {
            return $code;
        }

        $location = $this->findPropertyLocation($parseResult->getAst(), $propertyName);
        
        if ($location === null) {
            return $code;
        }

        $lines = explode("\n", $code);
        $lineIndex = $location['line'] - 1;
        
        if (!isset($lines[$lineIndex])) {
            return $code;
        }

        $typeString = $type->toString();
        
        // Pattern to match property declaration
        $pattern = '/(public|protected|private|var)\s+(\$' . preg_quote($propertyName, '/') . ')\b/';
        $replacement = '$1 ' . $typeString . ' $2';
        
        $lines[$lineIndex] = preg_replace($pattern, $replacement, $lines[$lineIndex], 1);
        
        return implode("\n", $lines);
    }

    /**
     * Fix implicitly nullable parameter
     */
    public function fixImplicitlyNullable(
        string $code,
        string $paramName,
        InferredType $type,
        ?string $methodName = null
    ): string {
        $nullableType = new InferredType(
            $type->type,
            true,
            $type->confidence,
            $type->source
        );

        return $this->addParameterType($code, $paramName, $nullableType, $methodName);
    }

    /**
     * Add types to closure
     */
    public function addClosureTypes(
        string $code,
        array $paramTypes = [],
        ?InferredType $returnType = null
    ): string {
        // For closures, we'd need more complex logic
        // For now, return unchanged
        return $code;
    }

    /**
     * Find parameter locations in AST
     */
    private function findParameterLocations(array $ast, string $paramName, ?string $methodName): array
    {
        $locations = [];
        
        $visitor = new class($paramName, $methodName, &$locations) extends NodeVisitorAbstract {
            public function __construct(
                private string $paramName,
                private ?string $methodName,
                private array &$locations
            ) {}

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_) {
                    if ($this->methodName !== null && $node->name->toString() !== $this->methodName) {
                        return null;
                    }

                    foreach ($node->params as $param) {
                        $name = $param->var instanceof Node\Expr\Variable ? $param->var->name : null;
                        
                        if ($name === $this->paramName && $param->type === null) {
                            $this->locations[] = [
                                'line' => $param->getStartLine(),
                                'isReference' => $param->byRef,
                                'isVariadic' => $param->variadic,
                            ];
                        }
                    }
                }
                
                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $locations;
    }

    /**
     * Find method location in AST
     */
    private function findMethodLocation(array $ast, string $methodName): ?array
    {
        $location = null;
        
        $visitor = new class($methodName, &$location) extends NodeVisitorAbstract {
            public function __construct(
                private string $methodName,
                private ?array &$location
            ) {}

            public function enterNode(Node $node)
            {
                if (($node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_) &&
                    $node->name->toString() === $this->methodName &&
                    $node->returnType === null) {
                    
                    $this->location = [
                        'line' => $node->getStartLine(),
                    ];
                }
                
                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $location;
    }

    /**
     * Find property location in AST
     */
    private function findPropertyLocation(array $ast, string $propertyName): ?array
    {
        $location = null;
        
        $visitor = new class($propertyName, &$location) extends NodeVisitorAbstract {
            public function __construct(
                private string $propertyName,
                private ?array &$location
            ) {}

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Property && $node->type === null) {
                    foreach ($node->props as $prop) {
                        if ($prop->name->toString() === $this->propertyName) {
                            $this->location = [
                                'line' => $node->getStartLine(),
                            ];
                        }
                    }
                }
                
                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $location;
    }
}
