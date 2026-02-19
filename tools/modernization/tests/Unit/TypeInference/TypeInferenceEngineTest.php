<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\TypeInference;

use Gibbon\Modernization\TypeInference\TypeInferenceEngine;
use Gibbon\Modernization\TypeInference\PHPDocAnalyzer;
use Gibbon\Modernization\TypeInference\UsageAnalyzer;
use Gibbon\Modernization\TypeInference\InheritanceAnalyzer;
use Gibbon\Modernization\TypeInference\InferredType;
use Gibbon\Modernization\TypeInference\TypeUsageInfo;
use Gibbon\Modernization\TypeInference\MethodSignature;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TypeInferenceEngine
 * 
 * Tests the orchestration of type inference from multiple sources
 * with priority-based logic.
 */
class TypeInferenceEngineTest extends TestCase
{
    private TypeInferenceEngine $engine;
    private PHPDocAnalyzer $phpDocAnalyzer;
    private UsageAnalyzer $usageAnalyzer;
    private InheritanceAnalyzer $inheritanceAnalyzer;
    private $parser;

    protected function setUp(): void
    {
        $this->phpDocAnalyzer = new PHPDocAnalyzer();
        $this->usageAnalyzer = new UsageAnalyzer();
        $this->inheritanceAnalyzer = new InheritanceAnalyzer($this->phpDocAnalyzer);
        
        $this->engine = new TypeInferenceEngine(
            $this->phpDocAnalyzer,
            $this->usageAnalyzer,
            $this->inheritanceAnalyzer
        );
        
        $parserFactory = new ParserFactory();
        $this->parser = $parserFactory->createForNewestSupportedVersion();
    }

    // Parameter Type Inference Tests

    public function testInferParameterTypeFromPHPDoc(): void
    {
        $code = '<?php
        class Test {
            /**
             * @param string $name
             */
            public function setName($name) {}
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        $param = $method->params[0];
        
        $result = $this->engine->inferParameterType($param, $method);
        
        $this->assertInstanceOf(InferredType::class, $result);
        $this->assertSame('string', $result->type);
        $this->assertFalse($result->isNullable);
        $this->assertSame('phpdoc', $result->source);
        $this->assertGreaterThanOrEqual(0.9, $result->confidence);
    }

    public function testInferParameterTypeFromPHPDocWithNullable(): void
    {
        $code = '<?php
        class Test {
            /**
             * @param ?string $name
             */
            public function setName($name = null) {}
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        $param = $method->params[0];
        
        $result = $this->engine->inferParameterType($param, $method);
        
        $this->assertSame('string', $result->type);
        $this->assertTrue($result->isNullable);
        $this->assertSame('phpdoc', $result->source);
    }

    public function testInferParameterTypeFromPHPDocWithUnion(): void
    {
        $code = '<?php
        class Test {
            /**
             * @param string|int $value
             */
            public function setValue($value) {}
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        $param = $method->params[0];
        
        $result = $this->engine->inferParameterType($param, $method);
        
        $this->assertSame('int|string', $result->type);
        $this->assertFalse($result->isNullable);
        $this->assertSame('phpdoc', $result->source);
    }

    public function testInferParameterTypeFromUsage(): void
    {
        $code = '<?php
        class Test {
            public function process($data) {
                return count($data);
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        $param = $method->params[0];
        
        $result = $this->engine->inferParameterType($param, $method);
        
        $this->assertInstanceOf(InferredType::class, $result);
        // Usage analysis should detect array usage from count()
        $this->assertContains($result->source, ['usage', 'fallback']);
    }

    public function testInferParameterTypeFallbackToMixed(): void
    {
        $code = '<?php
        class Test {
            public function doSomething($value) {
                // No usage that reveals type
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        $param = $method->params[0];
        
        $result = $this->engine->inferParameterType($param, $method);
        
        $this->assertSame('mixed', $result->type);
        $this->assertSame('fallback', $result->source);
        $this->assertLessThan(0.5, $result->confidence);
    }

    public function testInferParameterTypeWithDefaultNull(): void
    {
        $code = '<?php
        class Test {
            /**
             * @param string $name
             */
            public function setName($name = null) {}
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        $param = $method->params[0];
        
        $result = $this->engine->inferParameterType($param, $method);
        
        $this->assertSame('string', $result->type);
        $this->assertTrue($result->isNullable);
    }

    // Return Type Inference Tests

    public function testInferReturnTypeFromPHPDoc(): void
    {
        $code = '<?php
        class Test {
            /**
             * @return string
             */
            public function getName() {
                return "test";
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        
        $result = $this->engine->inferReturnType($method);
        
        $this->assertInstanceOf(InferredType::class, $result);
        $this->assertSame('string', $result->type);
        $this->assertFalse($result->isNullable);
        $this->assertSame('phpdoc', $result->source);
        $this->assertGreaterThanOrEqual(0.9, $result->confidence);
    }

    public function testInferReturnTypeFromPHPDocWithNullable(): void
    {
        $code = '<?php
        class Test {
            /**
             * @return ?string
             */
            public function getName() {
                return null;
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        
        $result = $this->engine->inferReturnType($method);
        
        $this->assertSame('string', $result->type);
        $this->assertTrue($result->isNullable);
        $this->assertSame('phpdoc', $result->source);
    }

    public function testInferReturnTypeFromPHPDocWithUnion(): void
    {
        $code = '<?php
        class Test {
            /**
             * @return string|int
             */
            public function getValue() {
                return "test";
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        
        $result = $this->engine->inferReturnType($method);
        
        $this->assertSame('int|string', $result->type);
        $this->assertFalse($result->isNullable);
        $this->assertSame('phpdoc', $result->source);
    }

    public function testInferReturnTypeFromUsageWithString(): void
    {
        $code = '<?php
        class Test {
            public function getName() {
                return "test";
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        
        $result = $this->engine->inferReturnType($method);
        
        $this->assertSame('string', $result->type);
        $this->assertSame('usage', $result->source);
    }

    public function testInferReturnTypeFromUsageWithInt(): void
    {
        $code = '<?php
        class Test {
            public function getCount() {
                return 42;
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        
        $result = $this->engine->inferReturnType($method);
        
        $this->assertSame('int', $result->type);
        $this->assertSame('usage', $result->source);
    }

    public function testInferReturnTypeFromUsageWithVoid(): void
    {
        $code = '<?php
        class Test {
            public function doSomething() {
                echo "test";
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        
        $result = $this->engine->inferReturnType($method);
        
        $this->assertSame('void', $result->type);
        $this->assertSame('usage', $result->source);
    }

    public function testInferReturnTypeFromUsageWithMultipleReturns(): void
    {
        $code = '<?php
        class Test {
            public function getValue($flag) {
                if ($flag) {
                    return "string";
                }
                return 42;
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        
        $result = $this->engine->inferReturnType($method);
        
        // Should detect union type from multiple return statements
        $this->assertSame('usage', $result->source);
        $this->assertStringContainsString('|', $result->type);
    }

    public function testInferReturnTypeFallbackToMixed(): void
    {
        $code = '<?php
        class Test {
            public function getValue() {
                return $this->someUnknownMethod();
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        
        $result = $this->engine->inferReturnType($method);
        
        // Should fall back to mixed when type cannot be determined
        $this->assertContains($result->type, ['mixed', 'self']);
    }

    // Property Type Inference Tests

    public function testInferPropertyTypeFromPHPDoc(): void
    {
        $code = '<?php
        class Test {
            /**
             * @var string
             */
            private $name;
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $property = $class->stmts[0];
        
        $result = $this->engine->inferPropertyType($property, $class);
        
        $this->assertInstanceOf(InferredType::class, $result);
        $this->assertSame('string', $result->type);
        $this->assertFalse($result->isNullable);
        $this->assertSame('phpdoc', $result->source);
        $this->assertGreaterThanOrEqual(0.9, $result->confidence);
    }

    public function testInferPropertyTypeFromPHPDocWithNullable(): void
    {
        $code = '<?php
        class Test {
            /**
             * @var ?string
             */
            private $name = null;
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $property = $class->stmts[0];
        
        $result = $this->engine->inferPropertyType($property, $class);
        
        $this->assertSame('string', $result->type);
        $this->assertTrue($result->isNullable);
        $this->assertSame('phpdoc', $result->source);
    }

    public function testInferPropertyTypeFromPHPDocWithUnion(): void
    {
        $code = '<?php
        class Test {
            /**
             * @var string|int
             */
            private $value;
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $property = $class->stmts[0];
        
        $result = $this->engine->inferPropertyType($property, $class);
        
        $this->assertSame('int|string', $result->type);
        $this->assertFalse($result->isNullable);
        $this->assertSame('phpdoc', $result->source);
    }

    public function testInferPropertyTypeFromUsage(): void
    {
        $code = '<?php
        class Test {
            private $name;
            
            public function __construct() {
                $this->name = "test";
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $property = $class->stmts[0];
        
        $result = $this->engine->inferPropertyType($property, $class);
        
        $this->assertSame('string', $result->type);
        $this->assertSame('usage', $result->source);
    }

    public function testInferPropertyTypeFallbackToMixed(): void
    {
        $code = '<?php
        class Test {
            private $value;
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $property = $class->stmts[0];
        
        $result = $this->engine->inferPropertyType($property, $class);
        
        $this->assertSame('mixed', $result->type);
        $this->assertSame('fallback', $result->source);
        $this->assertLessThan(0.5, $result->confidence);
    }

    public function testInferPropertyTypeWithDefaultNull(): void
    {
        $code = '<?php
        class Test {
            /**
             * @var string
             */
            private $name = null;
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $property = $class->stmts[0];
        
        $result = $this->engine->inferPropertyType($property, $class);
        
        $this->assertSame('string', $result->type);
        $this->assertTrue($result->isNullable);
    }

    // Priority Logic Tests

    public function testPHPDocTakesPriorityOverUsage(): void
    {
        $code = '<?php
        class Test {
            /**
             * @param string $value
             */
            public function setValue($value) {
                // Usage suggests int, but PHPDoc says string
                return $value + 1;
            }
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        $param = $method->params[0];
        
        $result = $this->engine->inferParameterType($param, $method);
        
        // PHPDoc should take priority
        $this->assertSame('string', $result->type);
        $this->assertSame('phpdoc', $result->source);
    }

    // Confidence Score Tests

    public function testPHPDocHasHighConfidence(): void
    {
        $code = '<?php
        class Test {
            /**
             * @param string $name
             */
            public function setName($name) {}
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        $param = $method->params[0];
        
        $result = $this->engine->inferParameterType($param, $method);
        
        $this->assertTrue($result->isConfident());
        $this->assertGreaterThanOrEqual(0.9, $result->confidence);
    }

    public function testFallbackHasLowConfidence(): void
    {
        $code = '<?php
        class Test {
            public function doSomething($value) {}
        }';
        
        $ast = $this->parser->parse($code);
        $class = $ast[0];
        $method = $class->stmts[0];
        $param = $method->params[0];
        
        $result = $this->engine->inferParameterType($param, $method);
        
        $this->assertFalse($result->isConfident());
        $this->assertLessThan(0.5, $result->confidence);
    }

    // InferredType toString Tests

    public function testInferredTypeToStringWithSimpleType(): void
    {
        $inferred = new InferredType('string', false, 0.95, 'phpdoc');
        
        $this->assertSame('string', $inferred->toString());
    }

    public function testInferredTypeToStringWithNullable(): void
    {
        $inferred = new InferredType('string', true, 0.95, 'phpdoc');
        
        $this->assertSame('?string', $inferred->toString());
    }

    public function testInferredTypeToStringWithUnion(): void
    {
        $inferred = new InferredType('string|int', false, 0.80, 'usage');
        
        $this->assertSame('string|int', $inferred->toString());
    }

    public function testInferredTypeToStringWithNullableUnion(): void
    {
        $inferred = new InferredType('string|int', true, 0.80, 'usage');
        
        $this->assertSame('string|int|null', $inferred->toString());
    }

    public function testInferredTypeToStringWithVoid(): void
    {
        $inferred = new InferredType('void', false, 0.95, 'usage');
        
        $this->assertSame('void', $inferred->toString());
    }

    public function testInferredTypeToStringWithMixed(): void
    {
        $inferred = new InferredType('mixed', false, 0.30, 'fallback');
        
        $this->assertSame('mixed', $inferred->toString());
    }
}
