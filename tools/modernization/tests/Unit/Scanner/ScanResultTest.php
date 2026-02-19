<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\Scanner;

use Gibbon\Modernization\Scanner\ScanResult;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ScanResult
 */
class ScanResultTest extends TestCase
{
    public function testConstructorAndPublicProperties(): void
    {
        $implicitlyNullable = [
            ['file' => 'test.php', 'line' => 10, 'function' => 'test', 'parameter' => 'x']
        ];
        $missingParams = [
            ['file' => 'test.php', 'line' => 15, 'function' => 'foo', 'parameter' => 'y']
        ];
        $missingReturns = [
            ['file' => 'test.php', 'line' => 20, 'function' => 'bar']
        ];
        $missingProps = [
            ['file' => 'test.php', 'line' => 25, 'class' => 'MyClass', 'property' => 'name']
        ];

        $result = new ScanResult(
            implicitlyNullableParams: $implicitlyNullable,
            missingParameterTypes: $missingParams,
            missingReturnTypes: $missingReturns,
            missingPropertyTypes: $missingProps,
            filesScanned: 5,
            issuesFound: 4
        );

        $this->assertSame($implicitlyNullable, $result->implicitlyNullableParams);
        $this->assertSame($missingParams, $result->missingParameterTypes);
        $this->assertSame($missingReturns, $result->missingReturnTypes);
        $this->assertSame($missingProps, $result->missingPropertyTypes);
        $this->assertSame(5, $result->filesScanned);
        $this->assertSame(4, $result->issuesFound);
        $this->assertEmpty($result->errors);
    }

    public function testConstructorWithErrors(): void
    {
        $errors = ['Error 1', 'Error 2'];

        $result = new ScanResult(
            implicitlyNullableParams: [],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 0,
            issuesFound: 0,
            errors: $errors
        );

        $this->assertSame($errors, $result->errors);
    }

    public function testGroupByDirectoryWithSingleDirectory(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [
                ['file' => '/path/to/file1.php', 'line' => 10, 'function' => 'test', 'parameter' => 'x'],
                ['file' => '/path/to/file2.php', 'line' => 15, 'function' => 'foo', 'parameter' => 'y']
            ],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 2,
            issuesFound: 2
        );

        $grouped = $result->groupByDirectory();

        $this->assertArrayHasKey('/path/to', $grouped);
        $this->assertCount(2, $grouped['/path/to']['implicitlyNullable']);
    }

    public function testGroupByDirectoryWithMultipleDirectories(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [
                ['file' => '/path/dir1/file1.php', 'line' => 10, 'function' => 'test', 'parameter' => 'x']
            ],
            missingParameterTypes: [
                ['file' => '/path/dir2/file2.php', 'line' => 15, 'function' => 'foo', 'parameter' => 'y']
            ],
            missingReturnTypes: [
                ['file' => '/path/dir1/file3.php', 'line' => 20, 'function' => 'bar']
            ],
            missingPropertyTypes: [
                ['file' => '/path/dir3/file4.php', 'line' => 25, 'class' => 'MyClass', 'property' => 'name']
            ],
            filesScanned: 4,
            issuesFound: 4
        );

        $grouped = $result->groupByDirectory();

        $this->assertArrayHasKey('/path/dir1', $grouped);
        $this->assertArrayHasKey('/path/dir2', $grouped);
        $this->assertArrayHasKey('/path/dir3', $grouped);
        
        $this->assertCount(1, $grouped['/path/dir1']['implicitlyNullable']);
        $this->assertCount(1, $grouped['/path/dir2']['missingParameters']);
        $this->assertCount(1, $grouped['/path/dir1']['missingReturns']);
        $this->assertCount(1, $grouped['/path/dir3']['missingProperties']);
    }

    public function testGroupByDirectoryInitializesAllCategories(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [
                ['file' => '/path/to/file.php', 'line' => 10, 'function' => 'test', 'parameter' => 'x']
            ],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 1,
            issuesFound: 1
        );

        $grouped = $result->groupByDirectory();

        $this->assertArrayHasKey('/path/to', $grouped);
        $this->assertArrayHasKey('implicitlyNullable', $grouped['/path/to']);
        $this->assertArrayHasKey('missingParameters', $grouped['/path/to']);
        $this->assertArrayHasKey('missingReturns', $grouped['/path/to']);
        $this->assertArrayHasKey('missingProperties', $grouped['/path/to']);
    }

    public function testToArrayContainsAllFields(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [['file' => 'test.php', 'line' => 10, 'function' => 'test', 'parameter' => 'x']],
            missingParameterTypes: [['file' => 'test.php', 'line' => 15, 'function' => 'foo', 'parameter' => 'y']],
            missingReturnTypes: [['file' => 'test.php', 'line' => 20, 'function' => 'bar']],
            missingPropertyTypes: [['file' => 'test.php', 'line' => 25, 'class' => 'MyClass', 'property' => 'name']],
            filesScanned: 1,
            issuesFound: 4,
            errors: ['Error 1']
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('implicitlyNullableParams', $array);
        $this->assertArrayHasKey('missingParameterTypes', $array);
        $this->assertArrayHasKey('missingReturnTypes', $array);
        $this->assertArrayHasKey('missingPropertyTypes', $array);
        $this->assertArrayHasKey('filesScanned', $array);
        $this->assertArrayHasKey('issuesFound', $array);
        $this->assertArrayHasKey('errors', $array);

        $this->assertSame(1, $array['filesScanned']);
        $this->assertSame(4, $array['issuesFound']);
        $this->assertCount(1, $array['errors']);
    }

    public function testToArrayWithEmptyResult(): void
    {
        $result = new ScanResult(
            implicitlyNullableParams: [],
            missingParameterTypes: [],
            missingReturnTypes: [],
            missingPropertyTypes: [],
            filesScanned: 0,
            issuesFound: 0
        );

        $array = $result->toArray();

        $this->assertEmpty($array['implicitlyNullableParams']);
        $this->assertEmpty($array['missingParameterTypes']);
        $this->assertEmpty($array['missingReturnTypes']);
        $this->assertEmpty($array['missingPropertyTypes']);
        $this->assertSame(0, $array['filesScanned']);
        $this->assertSame(0, $array['issuesFound']);
        $this->assertEmpty($array['errors']);
    }
}
