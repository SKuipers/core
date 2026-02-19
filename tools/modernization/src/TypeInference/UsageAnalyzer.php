<?php

declare(strict_types=1);

namespace Gibbon\Modernization\TypeInference;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * UsageAnalyzer - Infers types from how variables are used in code
 * 
 * Analyzes parameter usage within method bodies, return statements,
 * and property assignments to infer types from operations and method calls.
 * 
 * Requirements: 3.2, 4.2, 5.2
 */
class UsageAnalyzer
{
    private NodeFinder $nodeFinder;

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }

    /**
     * Analyze how a parameter is used within a method body
     * 
     * @param string $paramName Parameter name (without $)
     * @param Node\Stmt\ClassMethod|Node\Stmt\Function_ $method Method or function node
     * @return TypeUsageInfo Information about parameter usage
     */
    public function analyzeParameterUsage(string $paramName, $method): TypeUsageInfo
    {
        $usages = [];
        $stmts = $method->stmts ?? [];

        if (empty($stmts)) {
            return new TypeUsageInfo('mixed', 0.0, []);
        }

        // Find all usages of the parameter
        $paramVar = new Node\Expr\Variable($paramName);
        
        // Search for variable usage in the method body
        $this->findParameterUsages($stmts, $paramName, $usages);

        // Infer type from usages
        return $this->inferTypeFromUsages($usages);
    }

    /**
     * Analyze return statements in a method
     * 
     * @param Node\Stmt\ClassMethod|Node\Stmt\Function_ $method Method or function node
     * @return array<string> Array of inferred return types
     */
    public function analyzeReturnStatements($method): array
    {
        $returnTypes = [];
        $stmts = $method->stmts ?? [];

        if (empty($stmts)) {
            return ['void'];
        }

        // Find all return statements
        $returns = $this->nodeFinder->findInstanceOf($stmts, Node\Stmt\Return_::class);

        if (empty($returns)) {
            return ['void'];
        }

        foreach ($returns as $return) {
            if ($return->expr === null) {
                $returnTypes[] = 'void';
                continue;
            }

            $type = $this->inferTypeFromExpression($return->expr);
            if ($type !== null) {
                $returnTypes[] = $type;
            }
        }

        return array_unique($returnTypes);
    }

    /**
     * Analyze property assignments in a class
     * 
     * @param string $propertyName Property name (without $)
     * @param Node\Stmt\Class_ $class Class node
     * @return array<string> Array of inferred types from assignments
     */
    public function analyzePropertyAssignments(string $propertyName, Node\Stmt\Class_ $class): array
    {
        $types = [];

        // Find all assignments to this property
        $assignments = $this->nodeFinder->find($class->stmts, function (Node $node) use ($propertyName) {
            // Look for $this->propertyName = ...
            if ($node instanceof Node\Expr\Assign) {
                $var = $node->var;
                if ($var instanceof Node\Expr\PropertyFetch &&
                    $var->var instanceof Node\Expr\Variable &&
                    $var->var->name === 'this' &&
                    $var->name instanceof Node\Identifier &&
                    $var->name->name === $propertyName) {
                    return true;
                }
            }
            return false;
        });

        foreach ($assignments as $assignment) {
            if ($assignment instanceof Node\Expr\Assign) {
                $type = $this->inferTypeFromExpression($assignment->expr);
                if ($type !== null) {
                    $types[] = $type;
                }
            }
        }

        return array_unique($types);
    }

    /**
     * Find all usages of a parameter in statements
     * 
     * @param array<Node\Stmt> $stmts Statements to search
     * @param string $paramName Parameter name
     * @param array<array{type: string, operation: string}> &$usages Reference to usages array
     * @return void
     */
    private function findParameterUsages(array $stmts, string $paramName, array &$usages): void
    {
        foreach ($stmts as $stmt) {
            $this->findParameterUsagesInNode($stmt, $paramName, $usages);
        }
    }

    /**
     * Find parameter usages in a node recursively
     * 
     * @param Node $node Node to search
     * @param string $paramName Parameter name
     * @param array<array{type: string, operation: string}> &$usages Reference to usages array
     * @return void
     */
    private function findParameterUsagesInNode(Node $node, string $paramName, array &$usages): void
    {
        // Check if this node uses the parameter
        if ($node instanceof Node\Expr\Variable && $node->name === $paramName) {
            // Found a usage, check the context
            return;
        }

        // Check for method calls on the parameter
        if ($node instanceof Node\Expr\MethodCall &&
            $node->var instanceof Node\Expr\Variable &&
            $node->var->name === $paramName) {
            $usages[] = [
                'type' => 'method_call',
                'operation' => $node->name instanceof Node\Identifier ? $node->name->name : 'unknown'
            ];
        }

        // Check for array access
        if ($node instanceof Node\Expr\ArrayDimFetch &&
            $node->var instanceof Node\Expr\Variable &&
            $node->var->name === $paramName) {
            $usages[] = [
                'type' => 'array_access',
                'operation' => 'array_access'
            ];
        }

        // Check for binary operations
        if ($node instanceof Node\Expr\BinaryOp) {
            if ($node->left instanceof Node\Expr\Variable && $node->left->name === $paramName) {
                $usages[] = [
                    'type' => 'binary_op',
                    'operation' => $this->getBinaryOpType($node)
                ];
            }
            if ($node->right instanceof Node\Expr\Variable && $node->right->name === $paramName) {
                $usages[] = [
                    'type' => 'binary_op',
                    'operation' => $this->getBinaryOpType($node)
                ];
            }
        }

        // Check for function calls with parameter as argument
        if ($node instanceof Node\Expr\FuncCall &&
            $node->name instanceof Node\Name) {
            foreach ($node->args as $arg) {
                if ($arg->value instanceof Node\Expr\Variable && $arg->value->name === $paramName) {
                    $usages[] = [
                        'type' => 'function_arg',
                        'operation' => $node->name->toString()
                    ];
                }
            }
        }

        // Recursively search child nodes
        foreach ($node->getSubNodeNames() as $name) {
            $subNode = $node->$name;
            
            if ($subNode instanceof Node) {
                $this->findParameterUsagesInNode($subNode, $paramName, $usages);
            } elseif (is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
                        $this->findParameterUsagesInNode($item, $paramName, $usages);
                    }
                }
            }
        }
    }

    /**
     * Get the type of binary operation
     * 
     * @param Node\Expr\BinaryOp $op Binary operation node
     * @return string Operation type
     */
    private function getBinaryOpType(Node\Expr\BinaryOp $op): string
    {
        if ($op instanceof Node\Expr\BinaryOp\Plus ||
            $op instanceof Node\Expr\BinaryOp\Minus ||
            $op instanceof Node\Expr\BinaryOp\Mul ||
            $op instanceof Node\Expr\BinaryOp\Div) {
            return 'arithmetic';
        }

        if ($op instanceof Node\Expr\BinaryOp\Concat) {
            return 'string_concat';
        }

        if ($op instanceof Node\Expr\BinaryOp\BooleanAnd ||
            $op instanceof Node\Expr\BinaryOp\BooleanOr ||
            $op instanceof Node\Expr\BinaryOp\LogicalAnd ||
            $op instanceof Node\Expr\BinaryOp\LogicalOr) {
            return 'boolean';
        }

        return 'comparison';
    }

    /**
     * Infer type from usage patterns
     * 
     * @param array<array{type: string, operation: string}> $usages Array of usage information
     * @return TypeUsageInfo Type usage information
     */
    private function inferTypeFromUsages(array $usages): TypeUsageInfo
    {
        if (empty($usages)) {
            return new TypeUsageInfo('mixed', 0.0, $usages);
        }

        $typeHints = [];
        
        foreach ($usages as $usage) {
            switch ($usage['type']) {
                case 'array_access':
                    $typeHints[] = 'array';
                    break;
                    
                case 'binary_op':
                    if ($usage['operation'] === 'arithmetic') {
                        $typeHints[] = 'int|float';
                    } elseif ($usage['operation'] === 'string_concat') {
                        $typeHints[] = 'string';
                    } elseif ($usage['operation'] === 'boolean') {
                        $typeHints[] = 'bool';
                    }
                    break;
                    
                case 'method_call':
                    $typeHints[] = 'object';
                    break;
                    
                case 'function_arg':
                    // Specific function hints
                    $func = $usage['operation'];
                    if (in_array($func, ['strlen', 'substr', 'str_replace', 'trim'], true)) {
                        $typeHints[] = 'string';
                    } elseif (in_array($func, ['count', 'array_map', 'array_filter'], true)) {
                        $typeHints[] = 'array';
                    } elseif (in_array($func, ['is_null', 'empty', 'isset'], true)) {
                        $typeHints[] = 'mixed';
                    }
                    break;
            }
        }

        if (empty($typeHints)) {
            return new TypeUsageInfo('mixed', 0.0, $usages);
        }

        // Calculate confidence based on consistency
        $uniqueTypes = array_unique($typeHints);
        $confidence = count($typeHints) > 0 ? (1.0 / count($uniqueTypes)) : 0.0;

        // If all usages suggest the same type, high confidence
        if (count($uniqueTypes) === 1) {
            return new TypeUsageInfo($uniqueTypes[0], 0.8, $usages);
        }

        // Multiple types suggest union or mixed
        $inferredType = implode('|', $uniqueTypes);
        return new TypeUsageInfo($inferredType, 0.5, $usages);
    }

    /**
     * Infer type from an expression
     * 
     * @param Expr $expr Expression node
     * @return string|null Inferred type or null
     */
    private function inferTypeFromExpression(Expr $expr): ?string
    {
        // Scalar values
        if ($expr instanceof Node\Scalar\String_) {
            return 'string';
        }
        if ($expr instanceof Node\Scalar\Int_) {
            return 'int';
        }
        if ($expr instanceof Node\Scalar\Float_) {
            return 'float';
        }
        if ($expr instanceof Node\Expr\ConstFetch) {
            $name = $expr->name->toString();
            if (in_array(strtolower($name), ['true', 'false'], true)) {
                return 'bool';
            }
            if (strtolower($name) === 'null') {
                return 'null';
            }
        }

        // Array
        if ($expr instanceof Node\Expr\Array_) {
            return 'array';
        }

        // New instance
        if ($expr instanceof Node\Expr\New_ && $expr->class instanceof Node\Name) {
            return $expr->class->toString();
        }

        // Variable (return $this)
        if ($expr instanceof Node\Expr\Variable && $expr->name === 'this') {
            return 'self';
        }

        // Binary operations
        if ($expr instanceof Node\Expr\BinaryOp\Plus ||
            $expr instanceof Node\Expr\BinaryOp\Minus ||
            $expr instanceof Node\Expr\BinaryOp\Mul ||
            $expr instanceof Node\Expr\BinaryOp\Div) {
            return 'int|float';
        }

        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return 'string';
        }

        // Ternary
        if ($expr instanceof Node\Expr\Ternary) {
            $ifType = $expr->if !== null ? $this->inferTypeFromExpression($expr->if) : null;
            $elseType = $this->inferTypeFromExpression($expr->else);
            
            if ($ifType === $elseType && $ifType !== null) {
                return $ifType;
            }
            
            if ($ifType !== null && $elseType !== null) {
                return $ifType . '|' . $elseType;
            }
        }

        return null;
    }
}
