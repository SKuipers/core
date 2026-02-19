<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\TypeInference;

use Gibbon\Modernization\TypeInference\PHPDocAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PHPDocAnalyzer
 * 
 * Tests PHPDoc parsing, type extraction, and type normalization.
 */
class PHPDocAnalyzerTest extends TestCase
{
    private PHPDocAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new PHPDocAnalyzer();
    }

    // Parameter Type Extraction Tests

    public function testExtractParameterTypeWithSimpleType(): void
    {
        $phpDoc = '/**
         * @param string $name
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'name');
        
        $this->assertSame('string', $result);
    }

    public function testExtractParameterTypeWithMultipleParams(): void
    {
        $phpDoc = '/**
         * @param string $name
         * @param int $age
         * @param bool $active
         */';
        
        $this->assertSame('string', $this->analyzer->extractParameterType($phpDoc, 'name'));
        $this->assertSame('int', $this->analyzer->extractParameterType($phpDoc, 'age'));
        $this->assertSame('bool', $this->analyzer->extractParameterType($phpDoc, 'active'));
    }

    public function testExtractParameterTypeWithUnionType(): void
    {
        $phpDoc = '/**
         * @param string|int $value
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'value');
        
        $this->assertSame('int|string', $result);
    }

    public function testExtractParameterTypeWithNullableType(): void
    {
        $phpDoc = '/**
         * @param ?string $name
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'name');
        
        $this->assertSame('string', $result);
    }

    public function testExtractParameterTypeWithNullInUnion(): void
    {
        $phpDoc = '/**
         * @param string|null $name
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'name');
        
        $this->assertSame('string|null', $result);
    }

    public function testExtractParameterTypeWithArrayType(): void
    {
        $phpDoc = '/**
         * @param array $items
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'items');
        
        $this->assertSame('array', $result);
    }

    public function testExtractParameterTypeWithArrayNotation(): void
    {
        $phpDoc = '/**
         * @param string[] $names
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'names');
        
        $this->assertSame('array', $result);
    }

    public function testExtractParameterTypeWithGenericArray(): void
    {
        $phpDoc = '/**
         * @param array<string, int> $map
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'map');
        
        $this->assertSame('array', $result);
    }

    public function testExtractParameterTypeWithClassName(): void
    {
        $phpDoc = '/**
         * @param \DateTime $date
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'date');
        
        $this->assertSame('DateTime', $result);
    }

    public function testExtractParameterTypeNotFound(): void
    {
        $phpDoc = '/**
         * @param string $name
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'age');
        
        $this->assertNull($result);
    }

    public function testExtractParameterTypeWithNullPhpDoc(): void
    {
        $result = $this->analyzer->extractParameterType(null, 'name');
        
        $this->assertNull($result);
    }

    public function testExtractParameterTypeWithEmptyPhpDoc(): void
    {
        $result = $this->analyzer->extractParameterType('', 'name');
        
        $this->assertNull($result);
    }

    // Return Type Extraction Tests

    public function testExtractReturnTypeWithSimpleType(): void
    {
        $phpDoc = '/**
         * @return string
         */';
        
        $result = $this->analyzer->extractReturnType($phpDoc);
        
        $this->assertSame('string', $result);
    }

    public function testExtractReturnTypeWithVoid(): void
    {
        $phpDoc = '/**
         * @return void
         */';
        
        $result = $this->analyzer->extractReturnType($phpDoc);
        
        $this->assertSame('void', $result);
    }

    public function testExtractReturnTypeWithUnionType(): void
    {
        $phpDoc = '/**
         * @return string|int|null
         */';
        
        $result = $this->analyzer->extractReturnType($phpDoc);
        
        $this->assertSame('int|string|null', $result);
    }

    public function testExtractReturnTypeWithNullable(): void
    {
        $phpDoc = '/**
         * @return ?array
         */';
        
        $result = $this->analyzer->extractReturnType($phpDoc);
        
        $this->assertSame('array', $result);
    }

    public function testExtractReturnTypeNotFound(): void
    {
        $phpDoc = '/**
         * @param string $name
         */';
        
        $result = $this->analyzer->extractReturnType($phpDoc);
        
        $this->assertNull($result);
    }

    public function testExtractReturnTypeWithNullPhpDoc(): void
    {
        $result = $this->analyzer->extractReturnType(null);
        
        $this->assertNull($result);
    }

    // Property Type Extraction Tests

    public function testExtractPropertyTypeWithSimpleType(): void
    {
        $phpDoc = '/**
         * @var string
         */';
        
        $result = $this->analyzer->extractPropertyType($phpDoc);
        
        $this->assertSame('string', $result);
    }

    public function testExtractPropertyTypeWithUnionType(): void
    {
        $phpDoc = '/**
         * @var string|int
         */';
        
        $result = $this->analyzer->extractPropertyType($phpDoc);
        
        $this->assertSame('int|string', $result);
    }

    public function testExtractPropertyTypeWithNullable(): void
    {
        $phpDoc = '/**
         * @var ?DateTime
         */';
        
        $result = $this->analyzer->extractPropertyType($phpDoc);
        
        $this->assertSame('DateTime', $result);
    }

    public function testExtractPropertyTypeNotFound(): void
    {
        $phpDoc = '/**
         * Some comment
         */';
        
        $result = $this->analyzer->extractPropertyType($phpDoc);
        
        $this->assertNull($result);
    }

    // Type Normalization Tests

    public function testNormalizeTypeWithBoolean(): void
    {
        $phpDoc = '/**
         * @param boolean $flag
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'flag');
        
        $this->assertSame('bool', $result);
    }

    public function testNormalizeTypeWithInteger(): void
    {
        $phpDoc = '/**
         * @param integer $count
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'count');
        
        $this->assertSame('int', $result);
    }

    public function testNormalizeTypeWithDouble(): void
    {
        $phpDoc = '/**
         * @param double $price
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'price');
        
        $this->assertSame('float', $result);
    }

    public function testNormalizeTypeWithMixed(): void
    {
        $phpDoc = '/**
         * @param mixed $value
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'value');
        
        $this->assertSame('mixed', $result);
    }

    // Nullable Type Tests

    public function testIsNullableWithNullableShorthand(): void
    {
        $this->assertTrue($this->analyzer->isNullable('?string'));
    }

    public function testIsNullableWithUnionType(): void
    {
        $this->assertTrue($this->analyzer->isNullable('string|null'));
        $this->assertTrue($this->analyzer->isNullable('string|int|null'));
    }

    public function testIsNullableWithNonNullableType(): void
    {
        $this->assertFalse($this->analyzer->isNullable('string'));
        $this->assertFalse($this->analyzer->isNullable('string|int'));
    }

    public function testRemoveNullFromTypeWithNullableShorthand(): void
    {
        $result = $this->analyzer->removeNullFromType('?string');
        
        $this->assertSame('string', $result);
    }

    public function testRemoveNullFromTypeWithUnionType(): void
    {
        $result = $this->analyzer->removeNullFromType('string|null');
        
        $this->assertSame('string', $result);
    }

    public function testRemoveNullFromTypeWithMultipleTypes(): void
    {
        $result = $this->analyzer->removeNullFromType('string|int|null');
        
        $this->assertSame('string|int', $result);
    }

    public function testRemoveNullFromTypeWithNonNullableType(): void
    {
        $result = $this->analyzer->removeNullFromType('string');
        
        $this->assertSame('string', $result);
    }

    public function testRemoveNullFromTypeWithOnlyNull(): void
    {
        $result = $this->analyzer->removeNullFromType('null');
        
        $this->assertSame('mixed', $result);
    }

    // Edge Cases

    public function testExtractParameterTypeWithDescription(): void
    {
        $phpDoc = '/**
         * @param string $name The user name
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'name');
        
        $this->assertSame('string', $result);
    }

    public function testExtractParameterTypeWithComplexUnion(): void
    {
        $phpDoc = '/**
         * @param string|int|float|bool|null $value
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'value');
        
        // Should normalize and sort types
        $this->assertStringContainsString('string', $result);
        $this->assertStringContainsString('int', $result);
        $this->assertStringContainsString('float', $result);
        $this->assertStringContainsString('bool', $result);
        $this->assertStringContainsString('null', $result);
    }

    public function testExtractParameterTypeWithSelfType(): void
    {
        $phpDoc = '/**
         * @param self $instance
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'instance');
        
        $this->assertSame('self', $result);
    }

    public function testExtractParameterTypeWithStaticType(): void
    {
        $phpDoc = '/**
         * @param static $instance
         */';
        
        $result = $this->analyzer->extractParameterType($phpDoc, 'instance');
        
        $this->assertSame('static', $result);
    }
}
