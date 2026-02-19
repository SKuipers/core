<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\Scanner;

use Gibbon\Modernization\Scanner\ParseResult;
use PHPUnit\Framework\TestCase;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Scalar\String_;

class ParseResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $ast = [new Echo_([new String_('test')])];
        $result = ParseResult::success($ast, 'test.php');

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isError());
        $this->assertEquals($ast, $result->getAst());
        $this->assertEquals('test.php', $result->getFilename());
    }

    public function testSuccessResultWithoutFilename(): void
    {
        $ast = [new Echo_([new String_('test')])];
        $result = ParseResult::success($ast);

        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->getFilename());
    }

    public function testErrorResult(): void
    {
        $result = ParseResult::error(
            'Syntax error',
            'PARSE_ERROR',
            'test.php',
            10
        );

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isError());
        $this->assertEquals('Syntax error', $result->getErrorMessage());
        $this->assertEquals('PARSE_ERROR', $result->getErrorCode());
        $this->assertEquals('test.php', $result->getFilename());
        $this->assertEquals(10, $result->getErrorLine());
    }

    public function testErrorResultWithoutOptionalFields(): void
    {
        $result = ParseResult::error('Error message', 'ERROR_CODE');

        $this->assertTrue($result->isError());
        $this->assertNull($result->getFilename());
        $this->assertNull($result->getErrorLine());
    }

    public function testGetAstThrowsOnError(): void
    {
        $result = ParseResult::error('Error', 'ERROR_CODE');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot get AST from error result');
        $result->getAst();
    }

    public function testGetErrorMessageThrowsOnSuccess(): void
    {
        $result = ParseResult::success([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot get error message from success result');
        $result->getErrorMessage();
    }

    public function testGetErrorCodeThrowsOnSuccess(): void
    {
        $result = ParseResult::success([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot get error code from success result');
        $result->getErrorCode();
    }

    public function testGetFormattedErrorWithAllFields(): void
    {
        $result = ParseResult::error(
            'Unexpected token',
            'PARSE_ERROR',
            'example.php',
            42
        );

        $formatted = $result->getFormattedError();
        $this->assertStringContainsString('example.php', $formatted);
        $this->assertStringContainsString('line 42', $formatted);
        $this->assertStringContainsString('Unexpected token', $formatted);
        $this->assertStringContainsString('[PARSE_ERROR]', $formatted);
    }

    public function testGetFormattedErrorWithoutOptionalFields(): void
    {
        $result = ParseResult::error('Simple error', 'ERROR_CODE');

        $formatted = $result->getFormattedError();
        $this->assertEquals('Simple error [ERROR_CODE]', $formatted);
    }

    public function testGetFormattedErrorOnSuccess(): void
    {
        $result = ParseResult::success([]);

        $formatted = $result->getFormattedError();
        $this->assertEquals('No error', $formatted);
    }

    public function testGetFormattedErrorWithFilenameOnly(): void
    {
        $result = ParseResult::error(
            'File error',
            'FILE_ERROR',
            'test.php'
        );

        $formatted = $result->getFormattedError();
        $this->assertStringContainsString('test.php', $formatted);
        $this->assertStringContainsString('File error', $formatted);
        $this->assertStringNotContainsString('line', $formatted);
    }

    public function testGetFormattedErrorWithLineOnly(): void
    {
        $result = ParseResult::error(
            'Parse error',
            'PARSE_ERROR',
            null,
            15
        );

        $formatted = $result->getFormattedError();
        $this->assertStringContainsString('line 15', $formatted);
        $this->assertStringContainsString('Parse error', $formatted);
    }
}
