<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Integration\TypeInference;

use Gibbon\Modernization\Scanner\ASTParser;
use Gibbon\Modernization\TypeInference\PHPDocAnalyzer;
use PHPUnit\Framework\TestCase;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;

/**
 * Integration tests for PHPDocAnalyzer with PHP-Parser nodes
 * 
 * Tests that PHPDocAnalyzer correctly extracts type information from real PHP code.
 */
class PHPDocAnalyzerIntegrationTest extends TestCase
{
    private ASTParser $parser;
    private PHPDocAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->parser = new ASTParser();
        $this->analyzer = new PHPDocAnalyzer();
    }

    public function testExtractPhpDocFromMethodNode(): void
    {
        $code = '<?php
        class Example {
            /**
             * @param string $name
             * @return void
             */
            public function setName($name) {}
        }';

        $result = $this->parser->parseCode($code);
        $this->assertTrue($result->isSuccess());

        $ast = $result->getAst();
        $class = $ast[0];
        $this->assertInstanceOf(Class_::class, $class);

        $method = $class->stmts[0];
        $this->assertInstanceOf(ClassMethod::class, $method);

        $phpDoc = $this->analyzer->extractPhpDocFromNode($method);
        $this->assertNotNull($phpDoc);
        $this->assertStringContainsString('@param string $name', $phpDoc);
        $this->assertStringContainsString('@return void', $phpDoc);
    }

    public function testExtractParameterTypeFromMethodNode(): void
    {
        $code = '<?php
        class Example {
            /**
             * @param string $name
             * @param int $age
             */
            public function setData($name, $age) {}
        }';

        $result = $this->parser->parseCode($code);
        $this->assertTrue($result->isSuccess());

        $ast = $result->getAst();
        $class = $ast[0];
        $method = $class->stmts[0];

        $phpDoc = $this->analyzer->extractPhpDocFromNode($method);
        
        $nameType = $this->analyzer->extractParameterType($phpDoc, 'name');
        $this->assertSame('string', $nameType);

        $ageType = $this->analyzer->extractParameterType($phpDoc, 'age');
        $this->assertSame('int', $ageType);
    }

    public function testExtractReturnTypeFromMethodNode(): void
    {
        $code = '<?php
        class Example {
            /**
             * @return string|null
             */
            public function getName() {
                return null;
            }
        }';

        $result = $this->parser->parseCode($code);
        $this->assertTrue($result->isSuccess());

        $ast = $result->getAst();
        $class = $ast[0];
        $method = $class->stmts[0];

        $phpDoc = $this->analyzer->extractPhpDocFromNode($method);
        $returnType = $this->analyzer->extractReturnType($phpDoc);
        
        $this->assertSame('string|null', $returnType);
    }

    public function testExtractPropertyTypeFromPropertyNode(): void
    {
        $code = '<?php
        class Example {
            /**
             * @var string
             */
            private $name;

            /**
             * @var int|null
             */
            private $age;
        }';

        $result = $this->parser->parseCode($code);
        $this->assertTrue($result->isSuccess());

        $ast = $result->getAst();
        $class = $ast[0];
        
        $nameProperty = $class->stmts[0];
        $this->assertInstanceOf(Property::class, $nameProperty);
        $namePhpDoc = $this->analyzer->extractPhpDocFromNode($nameProperty);
        $nameType = $this->analyzer->extractPropertyType($namePhpDoc);
        $this->assertSame('string', $nameType);

        $ageProperty = $class->stmts[1];
        $this->assertInstanceOf(Property::class, $ageProperty);
        $agePhpDoc = $this->analyzer->extractPhpDocFromNode($ageProperty);
        $ageType = $this->analyzer->extractPropertyType($agePhpDoc);
        $this->assertSame('int|null', $ageType);
    }

    public function testExtractPhpDocFromNodeWithoutDocComment(): void
    {
        $code = '<?php
        class Example {
            public function noDoc() {}
        }';

        $result = $this->parser->parseCode($code);
        $this->assertTrue($result->isSuccess());

        $ast = $result->getAst();
        $class = $ast[0];
        $method = $class->stmts[0];

        $phpDoc = $this->analyzer->extractPhpDocFromNode($method);
        $this->assertNull($phpDoc);
    }

    public function testExtractComplexUnionTypes(): void
    {
        $code = '<?php
        class Example {
            /**
             * @param string|int|float|bool|null $value
             * @return array<string, mixed>
             */
            public function process($value) {
                return [];
            }
        }';

        $result = $this->parser->parseCode($code);
        $this->assertTrue($result->isSuccess());

        $ast = $result->getAst();
        $class = $ast[0];
        $method = $class->stmts[0];

        $phpDoc = $this->analyzer->extractPhpDocFromNode($method);
        
        $paramType = $this->analyzer->extractParameterType($phpDoc, 'value');
        $this->assertNotNull($paramType);
        $this->assertStringContainsString('string', $paramType);
        $this->assertStringContainsString('int', $paramType);
        $this->assertStringContainsString('null', $paramType);

        $returnType = $this->analyzer->extractReturnType($phpDoc);
        $this->assertSame('array', $returnType);
    }

    public function testExtractArrayNotationTypes(): void
    {
        $code = '<?php
        class Example {
            /**
             * @param string[] $names
             * @param int[] $ids
             * @return bool[]
             */
            public function process($names, $ids) {
                return [];
            }
        }';

        $result = $this->parser->parseCode($code);
        $this->assertTrue($result->isSuccess());

        $ast = $result->getAst();
        $class = $ast[0];
        $method = $class->stmts[0];

        $phpDoc = $this->analyzer->extractPhpDocFromNode($method);
        
        $namesType = $this->analyzer->extractParameterType($phpDoc, 'names');
        $this->assertSame('array', $namesType);

        $idsType = $this->analyzer->extractParameterType($phpDoc, 'ids');
        $this->assertSame('array', $idsType);

        $returnType = $this->analyzer->extractReturnType($phpDoc);
        $this->assertSame('array', $returnType);
    }

    public function testExtractClassNameTypes(): void
    {
        $code = '<?php
        class Example {
            /**
             * @param \DateTime $date
             * @param \DateTimeInterface $interface
             * @return \DateInterval
             */
            public function process($date, $interface) {
                return new \DateInterval("P1D");
            }
        }';

        $result = $this->parser->parseCode($code);
        $this->assertTrue($result->isSuccess());

        $ast = $result->getAst();
        $class = $ast[0];
        $method = $class->stmts[0];

        $phpDoc = $this->analyzer->extractPhpDocFromNode($method);
        
        $dateType = $this->analyzer->extractParameterType($phpDoc, 'date');
        $this->assertSame('DateTime', $dateType);

        $interfaceType = $this->analyzer->extractParameterType($phpDoc, 'interface');
        $this->assertSame('DateTimeInterface', $interfaceType);

        $returnType = $this->analyzer->extractReturnType($phpDoc);
        $this->assertSame('DateInterval', $returnType);
    }
}
