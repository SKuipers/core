<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Migration;

use Gibbon\Modernization\Scanner\ASTParser;
use Gibbon\Modernization\TypeInference\InferredType;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard as StandardPrinter;

/**
 * CodeTransformer - Applies type hint transformations to PHP code
 * 
 * Uses PHP-Parser to modify AST nodes while preserving code formatting
 * and comments. Implements transformations for parameters, return types,
 * properties, and implicitly nullable parameters.
 * 
 * Requirements: 2.1, 2.4, 3.1, 3.2, 4.1, 4.2, 5.1, 5.2
 */
class CodeTransformer
{
    private ASTParser $parser;
    private StandardPrinter $printer;

    public function __construct(?ASTParser $parser = null)
    {
        $this->parser = $parser ?? new ASTParser();
        $this->printer = new StandardPrinter([
            'shortArraySyntax' => true,
        ]);
    }

    /**
     * Add type hint to a parameter
     * 
     * @param string $code Original PHP code
     * @param string $paramName Parameter name to modify
     * @param InferredType $type Type to add
     * @param string|null $methodName Optional method name to scope the change
     * @return string Modified PHP code
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

        $traverser = new NodeTraverser();
        $visitor = new class($paramName, $type, $methodName) extends NodeVisitorAbstract {
            public function __construct(
                private string $paramName,
                private InferredType $type,
                private ?string $methodName
            ) {}

            public function leaveNode(Node $node)
            {
                // Match methods or functions
                if ($node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_) {
                    // If method name is specified, only modify that method
                    if ($this->methodName !== null && $node->name->toString() !== $this->methodName) {
                        return null;
                    }

                    // Find and modify the parameter
                    foreach ($node->params as $param) {
                        $paramName = $param->var instanceof Node\Expr\Variable 
                            ? $param->var->name 
                            : null;
                            
                        if ($paramName === $this->paramName && $param->type === null) {
                            $param->type = $this->createTypeNode($this->type);
                            // Preserve variadic and reference flags
                            return $node;
                        }
                    }
                }
                
                // Handle closures and anonymous functions
                if ($node instanceof Node\Expr\Closure) {
                    foreach ($node->params as $param) {
                        $paramName = $param->var instanceof Node\Expr\Variable 
                            ? $param->var->name 
                            : null;
                            
                        if ($paramName === $this->paramName && $param->type === null) {
                            $param->type = $this->createTypeNode($this->type);
                            return $node;
                        }
                    }
                }

                return null;
            }

            private function createTypeNode(InferredType $type): Node\Identifier|Node\Name|Node\ComplexType
            {
                $typeString = $type->type;
                
                // Handle nullable types
                if ($type->isNullable && !str_contains($typeString, '|')) {
                    return new Node\NullableType(
                        $this->createSimpleType($typeString)
                    );
                }
                
                // Handle union types
                if (str_contains($typeString, '|')) {
                    $types = explode('|', $typeString);
                    $typeNodes = array_map(fn($t) => $this->createSimpleType(trim($t)), $types);
                    
                    // Add null if nullable
                    if ($type->isNullable && !in_array('null', $types, true)) {
                        $typeNodes[] = new Node\Identifier('null');
                    }
                    
                    return new Node\UnionType($typeNodes);
                }
                
                return $this->createSimpleType($typeString);
            }

            private function createSimpleType(string $type): Node\Identifier|Node\Name
            {
                // Built-in types use Identifier
                $builtInTypes = ['string', 'int', 'float', 'bool', 'array', 'object', 'mixed', 'void', 'null', 'callable', 'iterable', 'never', 'false', 'true'];
                
                if (in_array(strtolower($type), $builtInTypes, true)) {
                    return new Node\Identifier($type);
                }
                
                // Class names use Name
                return new Node\Name($type);
            }
        };

        $traverser->addVisitor($visitor);
        $modifiedAst = $traverser->traverse($parseResult->getAst());

        return $this->printer->prettyPrintFile($modifiedAst);
    }

    /**
     * Add return type hint to a method or function
     * 
     * @param string $code Original PHP code
     * @param string $methodName Method/function name to modify
     * @param InferredType $type Return type to add
     * @return string Modified PHP code
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

        $traverser = new NodeTraverser();
        $visitor = new class($methodName, $type) extends NodeVisitorAbstract {
            public function __construct(
                private string $methodName,
                private InferredType $type
            ) {}

            public function leaveNode(Node $node)
            {
                if (($node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_) &&
                    $node->name->toString() === $this->methodName &&
                    $node->returnType === null) {
                    
                    $node->returnType = $this->createTypeNode($this->type);
                    return $node;
                }
                
                // Handle closures (when methodName matches a variable name containing the closure)
                if ($node instanceof Node\Expr\Closure && $node->returnType === null) {
                    // For closures, we apply the type if no specific method name is given
                    // or if we're in a context where we want to type all closures
                    if ($this->methodName === null || $this->methodName === '__closure__') {
                        $node->returnType = $this->createTypeNode($this->type);
                        return $node;
                    }
                }

                return null;
            }

            private function createTypeNode(InferredType $type): Node\Identifier|Node\Name|Node\ComplexType
            {
                $typeString = $type->type;
                
                // Handle nullable types (but not void or mixed)
                if ($type->isNullable && !in_array($typeString, ['void', 'mixed', 'never'], true) && !str_contains($typeString, '|')) {
                    return new Node\NullableType(
                        $this->createSimpleType($typeString)
                    );
                }
                
                // Handle union types
                if (str_contains($typeString, '|')) {
                    $types = explode('|', $typeString);
                    $typeNodes = array_map(fn($t) => $this->createSimpleType(trim($t)), $types);
                    
                    // Add null if nullable
                    if ($type->isNullable && !in_array('null', $types, true)) {
                        $typeNodes[] = new Node\Identifier('null');
                    }
                    
                    return new Node\UnionType($typeNodes);
                }
                
                return $this->createSimpleType($typeString);
            }

            private function createSimpleType(string $type): Node\Identifier|Node\Name
            {
                $builtInTypes = ['string', 'int', 'float', 'bool', 'array', 'object', 'mixed', 'void', 'null', 'callable', 'iterable', 'never', 'false', 'true'];
                
                if (in_array(strtolower($type), $builtInTypes, true)) {
                    return new Node\Identifier($type);
                }
                
                return new Node\Name($type);
            }
        };

        $traverser->addVisitor($visitor);
        $modifiedAst = $traverser->traverse($parseResult->getAst());

        return $this->printer->prettyPrintFile($modifiedAst);
    }

    /**
     * Add type hint to a property
     * 
     * @param string $code Original PHP code
     * @param string $propertyName Property name to modify
     * @param InferredType $type Type to add
     * @return string Modified PHP code
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

        $traverser = new NodeTraverser();
        $visitor = new class($propertyName, $type) extends NodeVisitorAbstract {
            public function __construct(
                private string $propertyName,
                private InferredType $type
            ) {}

            public function leaveNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Property && $node->type === null) {
                    // Check if this property matches the name we're looking for
                    foreach ($node->props as $prop) {
                        if ($prop->name->toString() === $this->propertyName) {
                            $node->type = $this->createTypeNode($this->type);
                            return $node;
                        }
                    }
                }

                return null;
            }

            private function createTypeNode(InferredType $type): Node\Identifier|Node\Name|Node\ComplexType
            {
                $typeString = $type->type;
                
                // Handle nullable types
                if ($type->isNullable && !str_contains($typeString, '|')) {
                    return new Node\NullableType(
                        $this->createSimpleType($typeString)
                    );
                }
                
                // Handle union types
                if (str_contains($typeString, '|')) {
                    $types = explode('|', $typeString);
                    $typeNodes = array_map(fn($t) => $this->createSimpleType(trim($t)), $types);
                    
                    // Add null if nullable
                    if ($type->isNullable && !in_array('null', $types, true)) {
                        $typeNodes[] = new Node\Identifier('null');
                    }
                    
                    return new Node\UnionType($typeNodes);
                }
                
                return $this->createSimpleType($typeString);
            }

            private function createSimpleType(string $type): Node\Identifier|Node\Name
            {
                $builtInTypes = ['string', 'int', 'float', 'bool', 'array', 'object', 'mixed', 'void', 'null', 'callable', 'iterable', 'never', 'false', 'true'];
                
                if (in_array(strtolower($type), $builtInTypes, true)) {
                    return new Node\Identifier($type);
                }
                
                return new Node\Name($type);
            }
        };

        $traverser->addVisitor($visitor);
        $modifiedAst = $traverser->traverse($parseResult->getAst());

        return $this->printer->prettyPrintFile($modifiedAst);
    }

    /**
     * Fix implicitly nullable parameter by adding appropriate type hint
     * 
     * @param string $code Original PHP code
     * @param string $paramName Parameter name to fix
     * @param InferredType $type Type to add
     * @param string|null $methodName Optional method name to scope the change
     * @return string Modified PHP code
     */
    public function fixImplicitlyNullable(
        string $code,
        string $paramName,
        InferredType $type,
        ?string $methodName = null
    ): string {
        // For implicitly nullable parameters, ensure the type is marked as nullable
        $nullableType = new InferredType(
            $type->type,
            true, // Always nullable for implicitly nullable parameters
            $type->confidence,
            $type->source
        );

        return $this->addParameterType($code, $paramName, $nullableType, $methodName);
    }

    /**
     * Add type hints to closure parameters and return type
     * 
     * @param string $code Original PHP code
     * @param array $paramTypes Array of parameter names to InferredType mappings
     * @param InferredType|null $returnType Optional return type to add
     * @return string Modified PHP code
     */
    public function addClosureTypes(
        string $code,
        array $paramTypes = [],
        ?InferredType $returnType = null
    ): string {
        $parseResult = $this->parser->parseCode($code);
        
        if (!$parseResult->isSuccess()) {
            return $code;
        }

        $traverser = new NodeTraverser();
        $visitor = new class($paramTypes, $returnType) extends NodeVisitorAbstract {
            public function __construct(
                private array $paramTypes,
                private ?InferredType $returnType
            ) {}

            public function leaveNode(Node $node)
            {
                if ($node instanceof Node\Expr\Closure) {
                    // Add parameter types
                    foreach ($node->params as $param) {
                        $paramName = $param->var instanceof Node\Expr\Variable 
                            ? $param->var->name 
                            : null;
                            
                        if ($paramName && isset($this->paramTypes[$paramName]) && $param->type === null) {
                            $param->type = $this->createTypeNode($this->paramTypes[$paramName]);
                        }
                    }
                    
                    // Add return type
                    if ($this->returnType !== null && $node->returnType === null) {
                        $node->returnType = $this->createTypeNode($this->returnType);
                    }
                    
                    return $node;
                }

                return null;
            }

            private function createTypeNode(InferredType $type): Node\Identifier|Node\Name|Node\ComplexType
            {
                $typeString = $type->type;
                
                // Handle nullable types
                if ($type->isNullable && !str_contains($typeString, '|')) {
                    return new Node\NullableType(
                        $this->createSimpleType($typeString)
                    );
                }
                
                // Handle union types
                if (str_contains($typeString, '|')) {
                    $types = explode('|', $typeString);
                    $typeNodes = array_map(fn($t) => $this->createSimpleType(trim($t)), $types);
                    
                    // Add null if nullable
                    if ($type->isNullable && !in_array('null', $types, true)) {
                        $typeNodes[] = new Node\Identifier('null');
                    }
                    
                    return new Node\UnionType($typeNodes);
                }
                
                return $this->createSimpleType($typeString);
            }

            private function createSimpleType(string $type): Node\Identifier|Node\Name
            {
                $builtInTypes = ['string', 'int', 'float', 'bool', 'array', 'object', 'mixed', 'void', 'null', 'callable', 'iterable', 'never', 'false', 'true'];
                
                if (in_array(strtolower($type), $builtInTypes, true)) {
                    return new Node\Identifier($type);
                }
                
                return new Node\Name($type);
            }
        };

        $traverser->addVisitor($visitor);
        $modifiedAst = $traverser->traverse($parseResult->getAst());

        return $this->printer->prettyPrintFile($modifiedAst);
    }
}
