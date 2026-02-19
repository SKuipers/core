<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\Scanner;

use Gibbon\Modernization\Scanner\ASTParser;
use Gibbon\Modernization\Scanner\CodeScanner;
use Gibbon\Modernization\Scanner\FileAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CodeScanner
 */
class CodeScannerTest extends TestCase
{
    private CodeScanner $scanner;
    private string $testDataDir;

    protected function setUp(): void
    {
        $astParser = new ASTParser();
        $fileAnalyzer = new FileAnalyzer($astParser);
        $this->scanner = new CodeScanner($fileAnalyzer);
        
        // Create temporary test directory
        $this->testDataDir = sys_get_temp_dir() . '/scanner_test_' . uniqid();
        mkdir($this->testDataDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testDataDir)) {
            $this->removeDirectory($this->testDataDir);
        }
    }

    public function testScanDirectoryWithNoPhpFiles(): void
    {
        $result = $this->scanner->scanDirectory($this->testDataDir);

        $this->assertSame(0, $result->filesScanned);
        $this->assertSame(0, $result->issuesFound);
        $this->assertEmpty($result->implicitlyNullableParams);
        $this->assertEmpty($result->missingParameterTypes);
        $this->assertEmpty($result->missingReturnTypes);
        $this->assertEmpty($result->missingPropertyTypes);
    }

    public function testScanDirectoryWithSingleFile(): void
    {
        // Create a test PHP file with issues
        $testFile = $this->testDataDir . '/test.php';
        file_put_contents($testFile, <<<'PHP'
<?php
function testFunction($param = null) {
    return $param;
}
PHP
        );

        $result = $this->scanner->scanDirectory($this->testDataDir);

        $this->assertSame(1, $result->filesScanned);
        $this->assertGreaterThan(0, $result->issuesFound);
        $this->assertNotEmpty($result->implicitlyNullableParams);
    }

    public function testScanDirectoryRecursively(): void
    {
        // Create nested directory structure
        $subDir = $this->testDataDir . '/subdir';
        mkdir($subDir, 0777, true);

        // Create PHP files in both directories
        file_put_contents($this->testDataDir . '/file1.php', '<?php function test1($x = null) {}');
        file_put_contents($subDir . '/file2.php', '<?php function test2($y = null) {}');

        $result = $this->scanner->scanDirectory($this->testDataDir);

        $this->assertSame(2, $result->filesScanned);
        $this->assertGreaterThanOrEqual(2, count($result->implicitlyNullableParams));
    }

    public function testScanDirectoryExcludesVendorByDefault(): void
    {
        // Create vendor directory
        $vendorDir = $this->testDataDir . '/vendor';
        mkdir($vendorDir, 0777, true);

        // Create PHP files
        file_put_contents($this->testDataDir . '/app.php', '<?php function app($x = null) {}');
        file_put_contents($vendorDir . '/library.php', '<?php function lib($y = null) {}');

        $result = $this->scanner->scanDirectory($this->testDataDir);

        // Should only scan app.php, not vendor/library.php
        $this->assertSame(1, $result->filesScanned);
    }

    public function testScanDirectoryWithCustomExcludePatterns(): void
    {
        // Create directories
        $testsDir = $this->testDataDir . '/tests';
        mkdir($testsDir, 0777, true);

        // Create PHP files
        file_put_contents($this->testDataDir . '/app.php', '<?php function app($x = null) {}');
        file_put_contents($testsDir . '/test.php', '<?php function test($y = null) {}');

        $result = $this->scanner->scanDirectory($this->testDataDir, ['tests/']);

        // Should only scan app.php, not tests/test.php
        $this->assertSame(1, $result->filesScanned);
    }

    public function testScanDirectoryWithMultipleExcludePatterns(): void
    {
        // Create directories
        mkdir($this->testDataDir . '/vendor', 0777, true);
        mkdir($this->testDataDir . '/tests', 0777, true);
        mkdir($this->testDataDir . '/src', 0777, true);

        // Create PHP files
        file_put_contents($this->testDataDir . '/vendor/lib.php', '<?php function lib() {}');
        file_put_contents($this->testDataDir . '/tests/test.php', '<?php function test() {}');
        file_put_contents($this->testDataDir . '/src/app.php', '<?php function app() {}');

        $result = $this->scanner->scanDirectory($this->testDataDir, ['vendor/', 'tests/']);

        // Should only scan src/app.php
        $this->assertSame(1, $result->filesScanned);
    }

    public function testScanDirectoryWithNonExistentPath(): void
    {
        $result = $this->scanner->scanDirectory('/non/existent/path');

        $this->assertSame(0, $result->filesScanned);
        $this->assertSame(0, $result->issuesFound);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('Directory not found', $result->errors[0]);
    }

    public function testScanFileDirectly(): void
    {
        $testFile = $this->testDataDir . '/direct.php';
        file_put_contents($testFile, <<<'PHP'
<?php
function directTest($param = null) {
    return $param;
}
PHP
        );

        $analysis = $this->scanner->scanFile($testFile);

        $this->assertTrue($analysis->isSuccess());
        $this->assertNotEmpty($analysis->getImplicitlyNullableParams());
    }

    public function testGroupByDirectory(): void
    {
        // Create nested structure
        $dir1 = $this->testDataDir . '/dir1';
        $dir2 = $this->testDataDir . '/dir2';
        mkdir($dir1, 0777, true);
        mkdir($dir2, 0777, true);

        file_put_contents($dir1 . '/file1.php', '<?php function test1($x = null) {}');
        file_put_contents($dir2 . '/file2.php', '<?php function test2($y = null) {}');

        $result = $this->scanner->scanDirectory($this->testDataDir);
        $grouped = $result->groupByDirectory();

        $this->assertArrayHasKey($dir1, $grouped);
        $this->assertArrayHasKey($dir2, $grouped);
        $this->assertNotEmpty($grouped[$dir1]['implicitlyNullable']);
        $this->assertNotEmpty($grouped[$dir2]['implicitlyNullable']);
    }

    public function testToArrayFormat(): void
    {
        $testFile = $this->testDataDir . '/array_test.php';
        file_put_contents($testFile, <<<'PHP'
<?php
function arrayTest($param = null) {
    return $param;
}
PHP
        );

        $result = $this->scanner->scanDirectory($this->testDataDir);
        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('implicitlyNullableParams', $array);
        $this->assertArrayHasKey('missingParameterTypes', $array);
        $this->assertArrayHasKey('missingReturnTypes', $array);
        $this->assertArrayHasKey('missingPropertyTypes', $array);
        $this->assertArrayHasKey('filesScanned', $array);
        $this->assertArrayHasKey('issuesFound', $array);
        $this->assertArrayHasKey('errors', $array);
    }

    public function testScanDirectoryIgnoresNonPhpFiles(): void
    {
        // Create various file types
        file_put_contents($this->testDataDir . '/test.php', '<?php function test() {}');
        file_put_contents($this->testDataDir . '/readme.txt', 'This is a text file');
        file_put_contents($this->testDataDir . '/config.json', '{}');
        file_put_contents($this->testDataDir . '/style.css', 'body {}');

        $result = $this->scanner->scanDirectory($this->testDataDir);

        // Should only scan the PHP file
        $this->assertSame(1, $result->filesScanned);
    }

    public function testScanDirectoryHandlesParseErrors(): void
    {
        // Create a PHP file with syntax error
        $testFile = $this->testDataDir . '/invalid.php';
        file_put_contents($testFile, '<?php function broken( {}');

        $result = $this->scanner->scanDirectory($this->testDataDir);

        // File should be scanned but produce an error
        $this->assertSame(0, $result->filesScanned); // Not counted as successfully scanned
        $this->assertNotEmpty($result->errors);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
