<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\TypeInference;

use Gibbon\Modernization\TypeInference\MethodSignature;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MethodSignature
 * 
 * Tests method signature representation and utility methods.
 */
class MethodSignatureTest extends TestCase
{
    public function testConstructor(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string', 'isNullable' => false, 'hasDefault' => false],
            ['name' => 'param2', 'type' => 'int', 'isNullable' => true, 'hasDefault' => true],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'bool');
        
        $this->assertSame('testMethod', $signature->methodName);
        $this->assertSame($parameters, $signature->parameters);
        $this->assertSame('bool', $signature->returnType);
    }

    public function testGetParameterType(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string', 'isNullable' => false, 'hasDefault' => false],
            ['name' => 'param2', 'type' => 'int', 'isNullable' => false, 'hasDefault' => false],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'void');
        
        $this->assertSame('string', $signature->getParameterType('param1'));
        $this->assertSame('int', $signature->getParameterType('param2'));
    }

    public function testGetParameterTypeNotFound(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string', 'isNullable' => false, 'hasDefault' => false],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'void');
        
        $this->assertNull($signature->getParameterType('nonExistent'));
    }

    public function testIsParameterNullable(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string', 'isNullable' => false, 'hasDefault' => false],
            ['name' => 'param2', 'type' => 'int', 'isNullable' => true, 'hasDefault' => false],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'void');
        
        $this->assertFalse($signature->isParameterNullable('param1'));
        $this->assertTrue($signature->isParameterNullable('param2'));
    }

    public function testIsParameterNullableNotFound(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string', 'isNullable' => false, 'hasDefault' => false],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'void');
        
        $this->assertFalse($signature->isParameterNullable('nonExistent'));
    }

    public function testGetRequiredParameterCount(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string', 'isNullable' => false, 'hasDefault' => false],
            ['name' => 'param2', 'type' => 'int', 'isNullable' => false, 'hasDefault' => false],
            ['name' => 'param3', 'type' => 'bool', 'isNullable' => false, 'hasDefault' => true],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'void');
        
        $this->assertSame(2, $signature->getRequiredParameterCount());
    }

    public function testGetRequiredParameterCountAllOptional(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string', 'isNullable' => false, 'hasDefault' => true],
            ['name' => 'param2', 'type' => 'int', 'isNullable' => false, 'hasDefault' => true],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'void');
        
        $this->assertSame(0, $signature->getRequiredParameterCount());
    }

    public function testGetRequiredParameterCountNoParameters(): void
    {
        $signature = new MethodSignature('testMethod', [], 'void');
        
        $this->assertSame(0, $signature->getRequiredParameterCount());
    }

    public function testToArray(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string', 'isNullable' => false, 'hasDefault' => false],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'int');
        
        $array = $signature->toArray();
        
        $this->assertSame('testMethod', $array['methodName']);
        $this->assertSame($parameters, $array['parameters']);
        $this->assertSame('int', $array['returnType']);
    }

    public function testToStringSimple(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string', 'isNullable' => false, 'hasDefault' => false],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'int');
        
        $string = $signature->toString();
        
        $this->assertSame('testMethod(string $param1): int', $string);
    }

    public function testToStringMultipleParameters(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string', 'isNullable' => false, 'hasDefault' => false],
            ['name' => 'param2', 'type' => 'int', 'isNullable' => false, 'hasDefault' => false],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'bool');
        
        $string = $signature->toString();
        
        $this->assertSame('testMethod(string $param1, int $param2): bool', $string);
    }

    public function testToStringWithDefaultValue(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string', 'isNullable' => false, 'hasDefault' => true],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'void');
        
        $string = $signature->toString();
        
        $this->assertSame('testMethod(string $param1 = ...): void', $string);
    }

    public function testToStringNoTypes(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => null, 'isNullable' => false, 'hasDefault' => false],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, null);
        
        $string = $signature->toString();
        
        $this->assertSame('testMethod($param1)', $string);
    }

    public function testToStringNoParameters(): void
    {
        $signature = new MethodSignature('testMethod', [], 'void');
        
        $string = $signature->toString();
        
        $this->assertSame('testMethod(): void', $string);
    }

    public function testToStringNullableType(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => '?string', 'isNullable' => true, 'hasDefault' => false],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, '?int');
        
        $string = $signature->toString();
        
        $this->assertSame('testMethod(?string $param1): ?int', $string);
    }

    public function testToStringUnionType(): void
    {
        $parameters = [
            ['name' => 'param1', 'type' => 'string|int', 'isNullable' => false, 'hasDefault' => false],
        ];
        
        $signature = new MethodSignature('testMethod', $parameters, 'string|int');
        
        $string = $signature->toString();
        
        $this->assertSame('testMethod(string|int $param1): string|int', $string);
    }
}

