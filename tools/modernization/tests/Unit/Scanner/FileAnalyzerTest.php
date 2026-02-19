<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\Scanner;

use Gibbon\Modernization\Scanner\ASTParser;
use Gibbon\Modernization\Scanner\FileAnalyzer;
use Gibbon\Modernization\Scanner\FileAnalysis;
use PHPUnit\Framework\TestCase;

class FileAnalyzerTest extends TestCase
{
    private FileAnalyzer $analyzer;
    private string $tempDir;

    protected function setUp(): void
    {
        $parser = new ASTParser();
        $this->analyzer = new FileAnalyzer($parser);
        $this->tempDir = sys_get_temp_dir() . '/file_analyzer_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function testAnalyzeValidFile(): void
    {
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, '<?php function test() {}');

        $result = $this->analyzer->analyze($filePath);

        $this->assertInstanceOf(FileAnalysis::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isError());
        $this->assertEquals($filePath, $result->getFilePath());
    }

    public function testAnalyzeNonExistentFile(): void
    {
        $result = $this->analyzer->analyze('/non/existent/file.php');

        $this->assertTrue($result->isError());
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('FILE_NOT_FOUND', $result->getErrorCode());
    }

    public function testFindImplicitlyNullableParameters(): void
    {
        $code = <<<'PHP'
<?php
function testFunction($param = null) {
    return $param;
}

class TestClass {
    public function testMethod($value = null) {
        return $value;
    }
}
PHP;

        $filePath = $this->tempDir . '/implicit_nullable.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath);

        $this->assertTrue($result->isSuccess());
        $implicitlyNullable = $result->getImplicitlyNullableParams();
        $this->assertCount(2, $implicitlyNullable);
        
        $this->assertEquals('testFunction', $implicitlyNullable[0]['function']);
        $this->assertEquals('param', $implicitlyNullable[0]['parameter']);
        
        $this->assertEquals('testMethod', $implicitlyNullable[1]['function']);
        $this->assertEquals('value', $implicitlyNullable[1]['parameter']);
    }

    public function testNotImplicitlyNullableWithExplicitNullableType(): void
    {
        $code = <<<'PHP'
<?php
function testFunction(?string $param = null) {
    return $param;
}
PHP;

        $filePath = $this->tempDir . '/explicit_nullable.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath);

        $this->assertTrue($result->isSuccess());
        $implicitlyNullable = $result->getImplicitlyNullableParams();
        $this->assertCount(0, $implicitlyNullable);
    }

    public function testNotImplicitlyNullableWithUnionType(): void
    {
        $code = <<<'PHP'
<?php
function testFunction(string|null $param = null) {
    return $param;
}
PHP;

        $filePath = $this->tempDir . '/union_nullable.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath);

        $this->assertTrue($result->isSuccess());
        $implicitlyNullable = $result->getImplicitlyNullableParams();
        $this->assertCount(0, $implicitlyNullable);
    }

    public function testImplicitlyNullableWithNonNullableType(): void
    {
        $code = <<<'PHP'
<?php
function testFunction(string $param = null) {
    return $param;
}
PHP;

        $filePath = $this->tempDir . '/non_nullable_with_null.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath);

        $this->assertTrue($result->isSuccess());
        $implicitlyNullable = $result->getImplicitlyNullableParams();
        $this->assertCount(1, $implicitlyNullable);
        $this->assertEquals('testFunction', $implicitlyNullable[0]['function']);
        $this->assertEquals('param', $implicitlyNullable[0]['parameter']);
    }

    public function testFindMissingParameterTypeHints(): void
    {
        $code = <<<'PHP'
<?php
function testFunction($param1, $param2) {
    return $param1 + $param2;
}

class TestClass {
    public function testMethod($value) {
        return $value;
    }
}
PHP;

        $filePath = $this->tempDir . '/missing_params.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath);

        $this->assertTrue($result->isSuccess());
        $missingTypeHints = $result->getMissingTypeHints();
        $this->assertCount(3, $missingTypeHints['parameters']);
        
        $this->assertEquals('testFunction', $missingTypeHints['parameters'][0]['function']);
        $this->assertEquals('param1', $missingTypeHints['parameters'][0]['parameter']);
        
        $this->assertEquals('testFunction', $missingTypeHints['parameters'][1]['function']);
        $this->assertEquals('param2', $missingTypeHints['parameters'][1]['parameter']);
        
        $this->assertEquals('TestClass', $missingTypeHints['parameters'][2]['class']);
        $this->assertEquals('testMethod', $missingTypeHints['parameters'][2]['method']);
        $this->assertEquals('value', $missingTypeHints['parameters'][2]['parameter']);
    }

    public function testFindMissingReturnTypeHints(): void
    {
        $code = <<<'PHP'
<?php
function testFunction() {
    return 42;
}

class TestClass {
    public function testMethod() {
        return "value";
    }
}
PHP;

        $filePath = $this->tempDir . '/missing_returns.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath);

        $this->assertTrue($result->isSuccess());
        $missingTypeHints = $result->getMissingTypeHints();
        $this->assertCount(2, $missingTypeHints['returns']);
        
        $this->assertEquals('testFunction', $missingTypeHints['returns'][0]['function']);
        
        $this->assertEquals('TestClass', $missingTypeHints['returns'][1]['class']);
        $this->assertEquals('testMethod', $missingTypeHints['returns'][1]['method']);
    }

    public function testFindMissingPropertyTypeHints(): void
    {
        $code = <<<'PHP'
<?php
class TestClass {
    private $property1;
    protected $property2;
    public $property3;
}
PHP;

        $filePath = $this->tempDir . '/missing_properties.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath);

        $this->assertTrue($result->isSuccess());
        $missingTypeHints = $result->getMissingTypeHints();
        $this->assertCount(3, $missingTypeHints['properties']);
        
        $this->assertEquals('TestClass', $missingTypeHints['properties'][0]['class']);
        $this->assertEquals('property1', $missingTypeHints['properties'][0]['property']);
        
        $this->assertEquals('TestClass', $missingTypeHints['properties'][1]['class']);
        $this->assertEquals('property2', $missingTypeHints['properties'][1]['property']);
        
        $this->assertEquals('TestClass', $missingTypeHints['properties'][2]['class']);
        $this->assertEquals('property3', $missingTypeHints['properties'][2]['property']);
    }

    public function testNoIssuesWithFullyTypedCode(): void
    {
        $code = <<<'PHP'
<?php
function testFunction(string $param): int {
    return 42;
}

class TestClass {
    private string $property;
    
    public function testMethod(int $value): string {
        return (string)$value;
    }
}
PHP;

        $filePath = $this->tempDir . '/fully_typed.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->hasIssues());
        $this->assertEquals(0, $result->getTotalIssues());
        $this->assertCount(0, $result->getImplicitlyNullableParams());
        $this->assertCount(0, $result->getMissingTypeHints()['parameters']);
        $this->assertCount(0, $result->getMissingTypeHints()['returns']);
        $this->assertCount(0, $result->getMissingTypeHints()['properties']);
    }

    public function testComplexScenarioWithMultipleIssues(): void
    {
        $code = <<<'PHP'
<?php
class ComplexClass {
    private $untypedProperty;
    private string $typedProperty;
    
    public function methodWithIssues($param1, string $param2 = null, ?int $param3 = null) {
        return $param1;
    }
    
    public function fullyTypedMethod(string $param): int {
        return 42;
    }
}

function globalFunction($x, $y = null) {
    return $x + $y;
}
PHP;

        $filePath = $this->tempDir . '/complex.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->hasIssues());
        
        // Should find 2 implicitly nullable parameters (string $param2 = null, $y = null)
        $implicitlyNullable = $result->getImplicitlyNullableParams();
        $this->assertCount(2, $implicitlyNullable);
        $this->assertEquals('methodWithIssues', $implicitlyNullable[0]['function']);
        $this->assertEquals('param2', $implicitlyNullable[0]['parameter']);
        $this->assertEquals('globalFunction', $implicitlyNullable[1]['function']);
        $this->assertEquals('y', $implicitlyNullable[1]['parameter']);
        
        $missingTypeHints = $result->getMissingTypeHints();
        
        // Should find missing parameter types: param1 in methodWithIssues, x and y in globalFunction
        $this->assertCount(3, $missingTypeHints['parameters']);
        
        // Should find missing return types: methodWithIssues and globalFunction
        $this->assertCount(2, $missingTypeHints['returns']);
        
        // Should find 1 missing property type: untypedProperty
        $this->assertCount(1, $missingTypeHints['properties']);
        $this->assertEquals('untypedProperty', $missingTypeHints['properties'][0]['property']);
    }
}
