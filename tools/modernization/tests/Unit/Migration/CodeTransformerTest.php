<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\Migration;

use Gibbon\Modernization\Migration\CodeTransformer;
use Gibbon\Modernization\TypeInference\InferredType;
use PHPUnit\Framework\TestCase;

class CodeTransformerTest extends TestCase
{
    private CodeTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new CodeTransformer();
    }

    public function testAddParameterTypeWithSimpleType(): void
    {
        $code = <<<'PHP'
<?php
function foo($param) {
    return $param;
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        $this->assertStringContainsString('function foo(string $param)', $result);
    }

    public function testAddParameterTypeWithNullableType(): void
    {
        $code = <<<'PHP'
<?php
function foo($param) {
    return $param;
}
PHP;

        $type = new InferredType('string', true, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        $this->assertStringContainsString('function foo(?string $param)', $result);
    }

    public function testAddParameterTypeWithUnionType(): void
    {
        $code = <<<'PHP'
<?php
function foo($param) {
    return $param;
}
PHP;

        $type = new InferredType('string|int', false, 0.90, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        $this->assertStringContainsString('function foo(string|int $param)', $result);
    }

    public function testAddParameterTypeWithNullableUnionType(): void
    {
        $code = <<<'PHP'
<?php
function foo($param) {
    return $param;
}
PHP;

        $type = new InferredType('string|int', true, 0.90, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        $this->assertStringContainsString('function foo(string|int|null $param)', $result);
    }

    public function testAddParameterTypeToSpecificMethod(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    public function bar($param) {
        return $param;
    }
    
    public function baz($param) {
        return $param;
    }
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type, 'bar');

        $this->assertStringContainsString('public function bar(string $param)', $result);
        $this->assertStringContainsString('public function baz($param)', $result);
    }

    public function testAddReturnTypeWithSimpleType(): void
    {
        $code = <<<'PHP'
<?php
function foo() {
    return "hello";
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addReturnType($code, 'foo', $type);

        $this->assertStringContainsString('function foo(): string', $result);
    }

    public function testAddReturnTypeWithNullableType(): void
    {
        $code = <<<'PHP'
<?php
function foo() {
    return null;
}
PHP;

        $type = new InferredType('string', true, 0.95, 'phpdoc');
        $result = $this->transformer->addReturnType($code, 'foo', $type);

        $this->assertStringContainsString('function foo(): ?string', $result);
    }

    public function testAddReturnTypeWithVoid(): void
    {
        $code = <<<'PHP'
<?php
function foo() {
    echo "hello";
}
PHP;

        $type = new InferredType('void', false, 0.95, 'usage');
        $result = $this->transformer->addReturnType($code, 'foo', $type);

        $this->assertStringContainsString('function foo(): void', $result);
    }

    public function testAddReturnTypeWithUnionType(): void
    {
        $code = <<<'PHP'
<?php
function foo($flag) {
    return $flag ? "hello" : 42;
}
PHP;

        $type = new InferredType('string|int', false, 0.90, 'usage');
        $result = $this->transformer->addReturnType($code, 'foo', $type);

        $this->assertStringContainsString('function foo($flag): string|int', $result);
    }

    public function testAddPropertyTypeWithSimpleType(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    private $name;
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addPropertyType($code, 'name', $type);

        $this->assertStringContainsString('private string $name', $result);
    }

    public function testAddPropertyTypeWithNullableType(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    private $name = null;
}
PHP;

        $type = new InferredType('string', true, 0.95, 'phpdoc');
        $result = $this->transformer->addPropertyType($code, 'name', $type);

        $this->assertStringContainsString('private ?string $name', $result);
    }

    public function testAddPropertyTypeWithUnionType(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    private $value;
}
PHP;

        $type = new InferredType('string|int', false, 0.90, 'usage');
        $result = $this->transformer->addPropertyType($code, 'value', $type);

        $this->assertStringContainsString('private string|int $value', $result);
    }

    public function testFixImplicitlyNullable(): void
    {
        $code = <<<'PHP'
<?php
function foo($param = null) {
    return $param;
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->fixImplicitlyNullable($code, 'param', $type);

        $this->assertStringContainsString('function foo(?string $param = null)', $result);
    }

    public function testFixImplicitlyNullableWithMixedType(): void
    {
        $code = <<<'PHP'
<?php
function foo($param = null) {
    return $param;
}
PHP;

        $type = new InferredType('mixed', false, 0.30, 'fallback');
        $result = $this->transformer->fixImplicitlyNullable($code, 'param', $type);

        $this->assertStringContainsString('function foo(?mixed $param = null)', $result);
    }

    public function testPreservesCodeFormatting(): void
    {
        $code = <<<'PHP'
<?php
/**
 * This is a function
 */
function foo($param) {
    // Comment inside
    return $param;
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        // Check that comments are preserved
        $this->assertStringContainsString('This is a function', $result);
        $this->assertStringContainsString('Comment inside', $result);
    }

    public function testDoesNotModifyExistingTypeHints(): void
    {
        $code = <<<'PHP'
<?php
function foo(int $param) {
    return $param;
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        // Should not change existing type hint
        $this->assertStringContainsString('function foo(int $param)', $result);
    }

    public function testHandlesClassTypes(): void
    {
        $code = <<<'PHP'
<?php
function foo($param) {
    return $param;
}
PHP;

        $type = new InferredType('DateTime', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        $this->assertStringContainsString('function foo(DateTime $param)', $result);
    }

    public function testHandlesArrayType(): void
    {
        $code = <<<'PHP'
<?php
function foo($param) {
    return $param;
}
PHP;

        $type = new InferredType('array', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        $this->assertStringContainsString('function foo(array $param)', $result);
    }

    public function testHandlesMixedType(): void
    {
        $code = <<<'PHP'
<?php
function foo($param) {
    return $param;
}
PHP;

        $type = new InferredType('mixed', false, 0.30, 'fallback');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        $this->assertStringContainsString('function foo(mixed $param)', $result);
    }

    public function testHandlesInvalidCode(): void
    {
        $code = '<?php this is not valid PHP';

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        // Should return original code unchanged
        $this->assertSame($code, $result);
    }

    // Edge case tests for Requirements 11.1-11.6

    public function testHandlesVariadicParameters(): void
    {
        $code = <<<'PHP'
<?php
function foo(...$args) {
    return $args;
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'args', $type);

        // Should preserve variadic syntax
        $this->assertStringContainsString('function foo(string ...$args)', $result);
    }

    public function testHandlesReferenceParameters(): void
    {
        $code = <<<'PHP'
<?php
function foo(&$param) {
    $param = "modified";
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        // Should preserve reference syntax
        $this->assertStringContainsString('function foo(string &$param)', $result);
    }

    public function testHandlesComplexDefaultValues(): void
    {
        $code = <<<'PHP'
<?php
function foo($param = ['key' => 'value']) {
    return $param;
}
PHP;

        $type = new InferredType('array', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        // Should preserve complex default value
        $this->assertStringContainsString('function foo(array $param = [', $result);
        $this->assertStringContainsString("'key' => 'value'", $result);
    }

    public function testHandlesClosureParameters(): void
    {
        $code = <<<'PHP'
<?php
$callback = function($param) {
    return $param;
};
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        // Should add type to closure parameter
        $this->assertStringContainsString('function (string $param)', $result);
    }

    public function testHandlesClosureReturnType(): void
    {
        $code = <<<'PHP'
<?php
$callback = function() {
    return "hello";
};
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addReturnType($code, '__closure__', $type);

        // Should add return type to closure
        $this->assertStringContainsString('function (): string', $result);
    }

    public function testAddClosureTypesWithMultipleParameters(): void
    {
        $code = <<<'PHP'
<?php
$callback = function($a, $b) {
    return $a + $b;
};
PHP;

        $paramTypes = [
            'a' => new InferredType('int', false, 0.95, 'phpdoc'),
            'b' => new InferredType('int', false, 0.95, 'phpdoc'),
        ];
        $returnType = new InferredType('int', false, 0.95, 'usage');
        
        $result = $this->transformer->addClosureTypes($code, $paramTypes, $returnType);

        $this->assertStringContainsString('function (int $a, int $b): int', $result);
    }

    public function testHandlesMagicMethodConstruct(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    public function __construct($param) {
        $this->param = $param;
    }
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type, '__construct');

        $this->assertStringContainsString('public function __construct(string $param)', $result);
    }

    public function testHandlesMagicMethodGet(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    public function __get($name) {
        return $this->data[$name] ?? null;
    }
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'name', $type, '__get');

        $this->assertStringContainsString('public function __get(string $name)', $result);
    }

    public function testHandlesMagicMethodSet(): void
    {
        $code = <<<'PHP'
<?php
class Foo {
    public function __set($name, $value) {
        $this->data[$name] = $value;
    }
}
PHP;

        $type = new InferredType('string', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'name', $type, '__set');

        $this->assertStringContainsString('public function __set(string $name, $value)', $result);
    }

    public function testHandlesVariadicWithNullableType(): void
    {
        $code = <<<'PHP'
<?php
function foo(...$args) {
    return $args;
}
PHP;

        $type = new InferredType('string', true, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'args', $type);

        $this->assertStringContainsString('function foo(?string ...$args)', $result);
    }

    public function testHandlesReferenceWithNullableType(): void
    {
        $code = <<<'PHP'
<?php
function foo(&$param) {
    $param = null;
}
PHP;

        $type = new InferredType('string', true, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'param', $type);

        $this->assertStringContainsString('function foo(?string &$param)', $result);
    }

    public function testHandlesClosureWithNullableReturnType(): void
    {
        $code = <<<'PHP'
<?php
$callback = function() {
    return null;
};
PHP;

        $type = new InferredType('string', true, 0.95, 'phpdoc');
        $result = $this->transformer->addReturnType($code, '__closure__', $type);

        $this->assertStringContainsString('function (): ?string', $result);
    }

    public function testHandlesComplexDefaultValueWithNull(): void
    {
        $code = <<<'PHP'
<?php
function foo($param = null) {
    return $param ?? [];
}
PHP;

        $type = new InferredType('array', false, 0.95, 'phpdoc');
        $result = $this->transformer->fixImplicitlyNullable($code, 'param', $type);

        $this->assertStringContainsString('function foo(?array $param = null)', $result);
    }

    public function testHandlesNestedClosures(): void
    {
        $code = <<<'PHP'
<?php
$outer = function($x) {
    return function($y) use ($x) {
        return $x + $y;
    };
};
PHP;

        $type = new InferredType('int', false, 0.95, 'phpdoc');
        $result = $this->transformer->addParameterType($code, 'x', $type);

        // Should add type to outer closure parameter
        $this->assertStringContainsString('function (int $x)', $result);
    }
}
