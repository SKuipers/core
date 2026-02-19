<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\Scanner;

use Gibbon\Modernization\Scanner\FileAnalysis;
use PHPUnit\Framework\TestCase;

class FileAnalysisTest extends TestCase
{
    public function testSuccessfulAnalysis(): void
    {
        $filePath = '/path/to/file.php';
        $implicitlyNullable = [
            ['line' => 10, 'function' => 'test', 'parameter' => 'param']
        ];
        $missingTypeHints = [
            'parameters' => [['line' => 20, 'function' => 'foo', 'parameter' => 'x']],
            'returns' => [['line' => 30, 'function' => 'bar']],
            'properties' => [['line' => 40, 'class' => 'Test', 'property' => 'prop']]
        ];

        $analysis = FileAnalysis::success($filePath, $implicitlyNullable, $missingTypeHints);

        $this->assertTrue($analysis->isSuccess());
        $this->assertFalse($analysis->isError());
        $this->assertEquals($filePath, $analysis->getFilePath());
        $this->assertEquals($implicitlyNullable, $analysis->getImplicitlyNullableParams());
        $this->assertEquals($missingTypeHints, $analysis->getMissingTypeHints());
    }

    public function testErrorAnalysis(): void
    {
        $filePath = '/path/to/file.php';
        $errorMessage = 'Parse error';
        $errorCode = 'PARSE_ERROR';

        $analysis = FileAnalysis::error($filePath, $errorMessage, $errorCode);

        $this->assertFalse($analysis->isSuccess());
        $this->assertTrue($analysis->isError());
        $this->assertEquals($filePath, $analysis->getFilePath());
        $this->assertEquals($errorMessage, $analysis->getErrorMessage());
        $this->assertEquals($errorCode, $analysis->getErrorCode());
    }

    public function testGetErrorMessageThrowsOnSuccess(): void
    {
        $analysis = FileAnalysis::success('/path/to/file.php', [], [
            'parameters' => [],
            'returns' => [],
            'properties' => []
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot get error message from success result');
        $analysis->getErrorMessage();
    }

    public function testGetErrorCodeThrowsOnSuccess(): void
    {
        $analysis = FileAnalysis::success('/path/to/file.php', [], [
            'parameters' => [],
            'returns' => [],
            'properties' => []
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot get error code from success result');
        $analysis->getErrorCode();
    }

    public function testGetTotalIssues(): void
    {
        $implicitlyNullable = [
            ['line' => 10, 'function' => 'test', 'parameter' => 'param1'],
            ['line' => 15, 'function' => 'test', 'parameter' => 'param2']
        ];
        $missingTypeHints = [
            'parameters' => [
                ['line' => 20, 'function' => 'foo', 'parameter' => 'x'],
                ['line' => 21, 'function' => 'foo', 'parameter' => 'y']
            ],
            'returns' => [
                ['line' => 30, 'function' => 'bar']
            ],
            'properties' => [
                ['line' => 40, 'class' => 'Test', 'property' => 'prop1'],
                ['line' => 41, 'class' => 'Test', 'property' => 'prop2'],
                ['line' => 42, 'class' => 'Test', 'property' => 'prop3']
            ]
        ];

        $analysis = FileAnalysis::success('/path/to/file.php', $implicitlyNullable, $missingTypeHints);

        // 2 implicitly nullable + 2 missing params + 1 missing return + 3 missing properties = 8
        $this->assertEquals(8, $analysis->getTotalIssues());
        $this->assertTrue($analysis->hasIssues());
    }

    public function testHasNoIssues(): void
    {
        $analysis = FileAnalysis::success('/path/to/file.php', [], [
            'parameters' => [],
            'returns' => [],
            'properties' => []
        ]);

        $this->assertEquals(0, $analysis->getTotalIssues());
        $this->assertFalse($analysis->hasIssues());
    }

    public function testErrorAnalysisHasNoIssues(): void
    {
        $analysis = FileAnalysis::error('/path/to/file.php', 'Error', 'ERROR_CODE');

        $this->assertEquals(0, $analysis->getTotalIssues());
        $this->assertFalse($analysis->hasIssues());
    }
}
