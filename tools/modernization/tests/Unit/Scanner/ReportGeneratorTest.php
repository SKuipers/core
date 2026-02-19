<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\Scanner;

use Gibbon\Modernization\Scanner\ReportGenerator;
use Gibbon\Modernization\Scanner\ScanResult;
use PHPUnit\Framework\TestCase;

class ReportGeneratorTest extends TestCase
{
    private ReportGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ReportGenerator();
    }

    public function testGenerateConsoleReportWithNoIssues(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 10,
            issuesFound: 0
        );

        $report = $this->generator->generateConsoleReport($result);

        $this->assertStringContainsString('Files Scanned: 10', $report);
        $this->assertStringContainsString('Total Issues Found: 0', $report);
        $this->assertStringContainsString('No issues found!', $report);
    }

    public function testGenerateConsoleReportWithImplicitlyNullableParams(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [
                [
                    'file' => '/path/to/src/User.php',
                    'line' => 42,
                    'function' => 'setName',
                    'parameter' => 'name',
                ],
            ],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 5,
            issuesFound: 1
        );

        $report = $this->generator->generateConsoleReport($result);

        $this->assertStringContainsString('Files Scanned: 5', $report);
        $this->assertStringContainsString('Total Issues Found: 1', $report);
        $this->assertStringContainsString('Implicitly Nullable Parameters', $report);
        $this->assertStringContainsString('User.php:42', $report);
        $this->assertStringContainsString('setName($name)', $report);
    }

    public function testGenerateConsoleReportWithMissingParameterTypes(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [],
            missingParameterTypes: [
                [
                    'file' => '/path/to/src/Calculator.php',
                    'line' => 15,
                    'class' => 'Calculator',
                    'method' => 'add',
                    'parameter' => 'a',
                ],
            ],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 3,
            issuesFound: 1
        );

        $report = $this->generator->generateConsoleReport($result);

        $this->assertStringContainsString('Missing Parameter Type Hints', $report);
        $this->assertStringContainsString('Calculator.php:15', $report);
        $this->assertStringContainsString('Calculator::add($a)', $report);
    }

    public function testGenerateConsoleReportWithMissingReturnTypes(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [],
            missingParameterTypes: [],
            missingReturnTypes: [
                [
                    'file' => '/path/to/src/Helper.php',
                    'line' => 20,
                    'function' => 'formatDate',
                ],
            ],
            missingPropertyTypes: [],
            filesScanned: 2,
            issuesFound: 1
        );

        $report = $this->generator->generateConsoleReport($result);

        $this->assertStringContainsString('Missing Return Type Hints', $report);
        $this->assertStringContainsString('Helper.php:20', $report);
        $this->assertStringContainsString('formatDate', $report);
    }

    public function testGenerateConsoleReportWithMissingPropertyTypes(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [
                [
                    'file' => '/path/to/src/Model.php',
                    'line' => 10,
                    'class' => 'Model',
                    'property' => 'data',
                ],
            ],
            filesScanned: 1,
            issuesFound: 1
        );

        $report = $this->generator->generateConsoleReport($result);

        $this->assertStringContainsString('Missing Property Type Hints', $report);
        $this->assertStringContainsString('Model.php:10', $report);
        $this->assertStringContainsString('Model::$data', $report);
    }

    public function testGenerateConsoleReportGroupsByDirectory(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [
                [
                    'file' => '/path/to/src/User.php',
                    'line' => 42,
                    'function' => 'setName',
                    'parameter' => 'name',
                ],
                [
                    'file' => '/path/to/lib/Helper.php',
                    'line' => 10,
                    'function' => 'process',
                    'parameter' => 'data',
                ],
            ],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 10,
            issuesFound: 2
        );

        $report = $this->generator->generateConsoleReport($result);

        $this->assertStringContainsString('/path/to/src', $report);
        $this->assertStringContainsString('/path/to/lib', $report);
    }

    public function testGenerateConsoleReportWithErrors(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 5,
            issuesFound: 0,
            errors: [
                'Failed to parse /path/to/broken.php',
                'Permission denied: /path/to/restricted.php',
            ]
        );

        $report = $this->generator->generateConsoleReport($result);

        $this->assertStringContainsString('Errors Encountered: 2', $report);
        $this->assertStringContainsString('Failed to parse /path/to/broken.php', $report);
        $this->assertStringContainsString('Permission denied: /path/to/restricted.php', $report);
    }

    public function testGenerateJsonReportStructure(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [
                [
                    'file' => '/path/to/src/User.php',
                    'line' => 42,
                    'function' => 'setName',
                    'parameter' => 'name',
                ],
            ],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 5,
            issuesFound: 1
        );

        $json = $this->generator->generateJsonReport($result);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('issues', $data);
        $this->assertArrayHasKey('byDirectory', $data);
        $this->assertArrayHasKey('errors', $data);

        $this->assertEquals(5, $data['summary']['filesScanned']);
        $this->assertEquals(1, $data['summary']['issuesFound']);
        $this->assertCount(1, $data['issues']['implicitlyNullableParams']);
    }

    public function testGenerateJsonReportIncludesGroupedData(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [
                [
                    'file' => '/path/to/src/User.php',
                    'line' => 42,
                    'function' => 'setName',
                    'parameter' => 'name',
                ],
            ],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 5,
            issuesFound: 1
        );

        $json = $this->generator->generateJsonReport($result);
        $data = json_decode($json, true);

        $this->assertArrayHasKey('byDirectory', $data);
        $this->assertArrayHasKey('/path/to/src', $data['byDirectory']);
        $this->assertCount(1, $data['byDirectory']['/path/to/src']['implicitlyNullable']);
    }

    public function testGenerateHtmlReportContainsBasicStructure(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 10,
            issuesFound: 0
        );

        $html = $this->generator->generateHtmlReport($result);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('PHP 8.4 Modernization Scan Report', $html);
        $this->assertStringContainsString('Files Scanned', $html);
        $this->assertStringContainsString('10', $html);
    }

    public function testGenerateHtmlReportWithNoIssues(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 10,
            issuesFound: 0
        );

        $html = $this->generator->generateHtmlReport($result);

        $this->assertStringContainsString('No issues found', $html);
        $this->assertStringContainsString('ready for PHP 8.4', $html);
    }

    public function testGenerateHtmlReportWithIssues(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [
                [
                    'file' => '/path/to/src/User.php',
                    'line' => 42,
                    'function' => 'setName',
                    'parameter' => 'name',
                ],
            ],
            missingParameterTypes: [
                [
                    'file' => '/path/to/src/Calculator.php',
                    'line' => 15,
                    'class' => 'Calculator',
                    'method' => 'add',
                    'parameter' => 'a',
                ],
            ],
            missingReturnTypes: [
                [
                    'file' => '/path/to/src/Helper.php',
                    'line' => 20,
                    'function' => 'formatDate',
                ],
            ],
            missingPropertyTypes: [
                [
                    'file' => '/path/to/src/Model.php',
                    'line' => 10,
                    'class' => 'Model',
                    'property' => 'data',
                ],
            ],
            filesScanned: 10,
            issuesFound: 4
        );

        $html = $this->generator->generateHtmlReport($result);

        $this->assertStringContainsString('Implicitly Nullable Parameters', $html);
        $this->assertStringContainsString('Missing Parameter Type Hints', $html);
        $this->assertStringContainsString('Missing Return Type Hints', $html);
        $this->assertStringContainsString('Missing Property Type Hints', $html);
        $this->assertStringContainsString('User.php', $html);
        $this->assertStringContainsString('Calculator.php', $html);
        $this->assertStringContainsString('Helper.php', $html);
        $this->assertStringContainsString('Model.php', $html);
    }

    public function testGenerateHtmlReportEscapesHtmlCharacters(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [
                [
                    'file' => '/path/to/src/<script>.php',
                    'line' => 42,
                    'function' => 'test<tag>',
                    'parameter' => 'param&value',
                ],
            ],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 1,
            issuesFound: 1
        );

        $html = $this->generator->generateHtmlReport($result);

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('test&lt;tag&gt;', $html);
        $this->assertStringContainsString('param&amp;value', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testGenerateHtmlReportWithErrors(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 5,
            issuesFound: 0,
            errors: [
                'Failed to parse file.php',
                'Permission denied',
            ]
        );

        $html = $this->generator->generateHtmlReport($result);

        $this->assertStringContainsString('Errors Encountered', $html);
        $this->assertStringContainsString('Failed to parse file.php', $html);
        $this->assertStringContainsString('Permission denied', $html);
    }

    public function testGenerateHtmlReportGroupsByDirectory(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [
                [
                    'file' => '/path/to/src/User.php',
                    'line' => 42,
                    'function' => 'setName',
                    'parameter' => 'name',
                ],
                [
                    'file' => '/path/to/lib/Helper.php',
                    'line' => 10,
                    'function' => 'process',
                    'parameter' => 'data',
                ],
            ],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 10,
            issuesFound: 2
        );

        $html = $this->generator->generateHtmlReport($result);

        $this->assertStringContainsString('/path/to/src', $html);
        $this->assertStringContainsString('/path/to/lib', $html);
    }
}
