<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\TypeInference;

use Gibbon\Modernization\TypeInference\UsageAnalyzer;
use Gibbon\Modernization\TypeInference\TypeUsageInfo;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UsageAnalyzer
 * 
 * Tests parameter usage analysis, return statement analysis,
 * and property assignment analysis.
 */
class UsageAnalyzerTest extends TestCase
{
    private UsageAnalyzer $analyzer;
    private $parser;

    protected function setUp(): void
    {
        $this->analyzer = new UsageAnalyzer();
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    // Parameter Usage Analysis Tests

    public function testAnalyzeParameterUsageWithArrayAccess(): void
    {
        $code = '<?php
        function test($data) {
            return $data[0];
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeParameterUsage('data', $function);
        
        $this->assertInstanceOf(TypeUsageInfo::class, $result);
        $this->assertStringContainsString('array', $result->inferredType);
    }

    public function testAnalyzeParameterUsageWithMethodCall(): void
    {
        $code = '<?php
        function test($obj) {
            return $obj->getName();
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeParameterUsage('obj', $function);
        
        $this->assertInstanceOf(TypeUsageInfo::class, $result);
        $this->assertStringContainsString('object', $result->inferredType);
    }

    public function testAnalyzeParameterUsageWithStringConcat(): void
    {
        $code = '<?php
        function test($name) {
            return "Hello " . $name;
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeParameterUsage('name', $function);
        
        $this->assertInstanceOf(TypeUsageInfo::class, $result);
        $this->assertStringContainsString('string', $result->inferredType);
    }

    public function testAnalyzeParameterUsageWithArithmetic(): void
    {
        $code = '<?php
        function test($num) {
            return $num + 10;
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeParameterUsage('num', $function);
        
        $this->assertInstanceOf(TypeUsageInfo::class, $result);
        $this->assertTrue(
            str_contains($result->inferredType, 'int') || 
            str_contains($result->inferredType, 'float')
        );
    }

    public function testAnalyzeParameterUsageWithStringFunction(): void
    {
        $code = '<?php
        function test($text) {
            return strlen($text);
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeParameterUsage('text', $function);
        
        $this->assertInstanceOf(TypeUsageInfo::class, $result);
        $this->assertStringContainsString('string', $result->inferredType);
    }

    public function testAnalyzeParameterUsageWithArrayFunction(): void
    {
        $code = '<?php
        function test($items) {
            return count($items);
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeParameterUsage('items', $function);
        
        $this->assertInstanceOf(TypeUsageInfo::class, $result);
        $this->assertStringContainsString('array', $result->inferredType);
    }

    public function testAnalyzeParameterUsageWithNoUsage(): void
    {
        $code = '<?php
        function test($unused) {
            return "hello";
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeParameterUsage('unused', $function);
        
        $this->assertInstanceOf(TypeUsageInfo::class, $result);
        $this->assertSame('mixed', $result->inferredType);
        $this->assertSame(0.0, $result->confidence);
    }

    public function testAnalyzeParameterUsageWithEmptyBody(): void
    {
        $code = '<?php
        function test($param) {}';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeParameterUsage('param', $function);
        
        $this->assertInstanceOf(TypeUsageInfo::class, $result);
        $this->assertSame('mixed', $result->inferredType);
    }

    // Return Statement Analysis Tests

    public function testAnalyzeReturnStatementsWithString(): void
    {
        $code = '<?php
        function test() {
            return "hello";
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertContains('string', $result);
    }

    public function testAnalyzeReturnStatementsWithInt(): void
    {
        $code = '<?php
        function test() {
            return 42;
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertContains('int', $result);
    }

    public function testAnalyzeReturnStatementsWithFloat(): void
    {
        $code = '<?php
        function test() {
            return 3.14;
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertContains('float', $result);
    }

    public function testAnalyzeReturnStatementsWithBool(): void
    {
        $code = '<?php
        function test() {
            return true;
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertContains('bool', $result);
    }

    public function testAnalyzeReturnStatementsWithNull(): void
    {
        $code = '<?php
        function test() {
            return null;
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertContains('null', $result);
    }

    public function testAnalyzeReturnStatementsWithArray(): void
    {
        $code = '<?php
        function test() {
            return [1, 2, 3];
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertContains('array', $result);
    }

    public function testAnalyzeReturnStatementsWithNewInstance(): void
    {
        $code = '<?php
        function test() {
            return new DateTime();
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertContains('DateTime', $result);
    }

    public function testAnalyzeReturnStatementsWithThis(): void
    {
        $code = '<?php
        class Test {
            public function test() {
                return $this;
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        
        $result = $this->analyzer->analyzeReturnStatements($method);
        
        $this->assertContains('self', $result);
    }

    public function testAnalyzeReturnStatementsWithVoid(): void
    {
        $code = '<?php
        function test() {
            return;
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertContains('void', $result);
    }

    public function testAnalyzeReturnStatementsWithNoReturn(): void
    {
        $code = '<?php
        function test() {
            echo "hello";
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertContains('void', $result);
    }

    public function testAnalyzeReturnStatementsWithMultipleTypes(): void
    {
        $code = '<?php
        function test($flag) {
            if ($flag) {
                return "string";
            }
            return 42;
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertContains('string', $result);
        $this->assertContains('int', $result);
    }

    public function testAnalyzeReturnStatementsWithArithmetic(): void
    {
        $code = '<?php
        function test() {
            return 10 + 5;
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertTrue(
            in_array('int|float', $result, true) ||
            (in_array('int', $result, true) && in_array('float', $result, true))
        );
    }

    public function testAnalyzeReturnStatementsWithStringConcat(): void
    {
        $code = '<?php
        function test() {
            return "Hello " . "World";
        }';
        
        $ast = $this->parser->parse($code);
        $function = $ast[0];
        
        $result = $this->analyzer->analyzeReturnStatements($function);
        
        $this->assertContains('string', $result);
    }

    // Property Assignment Analysis Tests

    public function testAnalyzePropertyAssignmentsWithString(): void
    {
        $code = '<?php
        class Test {
            private $name;
            
            public function __construct() {
                $this->name = "John";
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        
        $result = $this->analyzer->analyzePropertyAssignments('name', $class);
        
        $this->assertContains('string', $result);
    }

    public function testAnalyzePropertyAssignmentsWithInt(): void
    {
        $code = '<?php
        class Test {
            private $age;
            
            public function setAge() {
                $this->age = 25;
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        
        $result = $this->analyzer->analyzePropertyAssignments('age', $class);
        
        $this->assertContains('int', $result);
    }

    public function testAnalyzePropertyAssignmentsWithMultipleTypes(): void
    {
        $code = '<?php
        class Test {
            private $value;
            
            public function setString() {
                $this->value = "text";
            }
            
            public function setInt() {
                $this->value = 42;
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        
        $result = $this->analyzer->analyzePropertyAssignments('value', $class);
        
        $this->assertContains('string', $result);
        $this->assertContains('int', $result);
    }

    public function testAnalyzePropertyAssignmentsWithNoAssignments(): void
    {
        $code = '<?php
        class Test {
            private $unused;
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        
        $result = $this->analyzer->analyzePropertyAssignments('unused', $class);
        
        $this->assertEmpty($result);
    }

    public function testAnalyzePropertyAssignmentsWithArray(): void
    {
        $code = '<?php
        class Test {
            private $items;
            
            public function __construct() {
                $this->items = [];
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        
        $result = $this->analyzer->analyzePropertyAssignments('items', $class);
        
        $this->assertContains('array', $result);
    }

    public function testAnalyzePropertyAssignmentsWithObject(): void
    {
        $code = '<?php
        class Test {
            private $date;
            
            public function __construct() {
                $this->date = new DateTime();
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        
        $result = $this->analyzer->analyzePropertyAssignments('date', $class);
        
        $this->assertContains('DateTime', $result);
    }

    // TypeUsageInfo Tests

    public function testTypeUsageInfoIsConfident(): void
    {
        $info = new TypeUsageInfo('string', 0.8, []);
        
        $this->assertTrue($info->isConfident());
        $this->assertTrue($info->isConfident(0.7));
        $this->assertFalse($info->isConfident(0.9));
    }

    public function testTypeUsageInfoGetUsageDescription(): void
    {
        $usages = [
            ['type' => 'array_access', 'operation' => 'array_access'],
            ['type' => 'method_call', 'operation' => 'getName']
        ];
        
        $info = new TypeUsageInfo('array', 0.8, $usages);
        $description = $info->getUsageDescription();
        
        $this->assertStringContainsString('array_access', $description);
        $this->assertStringContainsString('getName', $description);
    }

    public function testTypeUsageInfoToArray(): void
    {
        $usages = [['type' => 'array_access', 'operation' => 'array_access']];
        $info = new TypeUsageInfo('array', 0.8, $usages);
        
        $array = $info->toArray();
        
        $this->assertSame('array', $array['inferredType']);
        $this->assertSame(0.8, $array['confidence']);
        $this->assertSame($usages, $array['usages']);
    }
}
