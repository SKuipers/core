<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\Scanner;

use Gibbon\Modernization\Scanner\ASTParser;
use Gibbon\Modernization\Scanner\ParseResult;
use PHPUnit\Framework\TestCase;
use PhpParser\Node;

class ASTParserTest extends TestCase
{
    private ASTParser $parser;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->parser = new ASTParser();
        $this->tempDir = sys_get_temp_dir() . '/ast_parser_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
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

    public function testParseValidPhpCode(): void
    {
        $code = '<?php function test() { return 42; }';
        $result = $this->parser->parseCode($code);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isError());
        $this->assertIsArray($result->getAst());
        $this->assertNotEmpty($result->getAst());
        $this->assertContainsOnlyInstancesOf(Node::class, $result->getAst());
    }

    public function testParseValidPhpFile(): void
    {
        $filePath = $this->tempDir . '/valid.php';
        file_put_contents($filePath, '<?php class TestClass { public function method() {} }');

        $result = $this->parser->parseFile($filePath);

        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getAst());
        $this->assertEquals($filePath, $result->getFilename());
    }

    public function testParseInvalidPhpCode(): void
    {
        $code = '<?php function test() { invalid syntax }';
        $result = $this->parser->parseCode($code, 'test.php');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isError());
        $this->assertEquals('PARSE_ERROR', $result->getErrorCode());
        $this->assertNotEmpty($result->getErrorMessage());
        $this->assertEquals('test.php', $result->getFilename());
    }

    public function testParseNonExistentFile(): void
    {
        $result = $this->parser->parseFile('/non/existent/file.php');

        $this->assertTrue($result->isError());
        $this->assertEquals('FILE_NOT_FOUND', $result->getErrorCode());
        $this->assertStringContainsString('File not found', $result->getErrorMessage());
    }

    public function testParseUnreadableFile(): void
    {
        $filePath = $this->tempDir . '/unreadable.php';
        file_put_contents($filePath, '<?php echo "test";');
        chmod($filePath, 0000);

        $result = $this->parser->parseFile($filePath);

        $this->assertTrue($result->isError());
        $this->assertEquals('FILE_NOT_READABLE', $result->getErrorCode());

        // Restore permissions for cleanup
        chmod($filePath, 0644);
    }

    public function testParseEmptyCode(): void
    {
        $result = $this->parser->parseCode('');

        // PHP-Parser returns an empty array for empty code, which is valid
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getAst());
        $this->assertEmpty($result->getAst());
    }

    public function testParseCodeWithFilename(): void
    {
        $code = '<?php $x = 1;';
        $filename = 'example.php';
        $result = $this->parser->parseCode($code, $filename);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals($filename, $result->getFilename());
    }

    public function testGetParser(): void
    {
        $parser = $this->parser->getParser();
        $this->assertInstanceOf(\PhpParser\Parser::class, $parser);
    }

    public function testParseComplexPhpCode(): void
    {
        $code = <<<'PHP'
<?php
namespace Test;

class Example {
    private string $property;
    
    public function __construct(string $value) {
        $this->property = $value;
    }
    
    public function getValue(): string {
        return $this->property;
    }
}
PHP;

        $result = $this->parser->parseCode($code);

        $this->assertTrue($result->isSuccess());
        $ast = $result->getAst();
        $this->assertNotEmpty($ast);
    }

    public function testParseCodeWithSyntaxError(): void
    {
        $code = '<?php function test() { return }'; // Missing semicolon and value
        $result = $this->parser->parseCode($code);

        $this->assertTrue($result->isError());
        $this->assertEquals('PARSE_ERROR', $result->getErrorCode());
        $this->assertNotNull($result->getErrorLine());
    }
}
