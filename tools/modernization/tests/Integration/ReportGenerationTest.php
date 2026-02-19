<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Integration;

use Gibbon\Modernization\Scanner\ASTParser;
use Gibbon\Modernization\Scanner\CodeScanner;
use Gibbon\Modernization\Scanner\FileAnalyzer;
use Gibbon\Modernization\Scanner\ReportGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for report generation workflow
 */
class ReportGenerationTest extends TestCase
{
    private CodeScanner $scanner;
    private ReportGenerator $reportGenerator;
    private string $testDataDir;

    protected function setUp(): void
    {
        $astParser = new ASTParser();
        $fileAnalyzer = new FileAnalyzer($astParser);
        $this->scanner = new CodeScanner($fileAnalyzer);
        $this->reportGenerator = new ReportGenerator();
        
        // Create temporary test directory
        $this->testDataDir = sys_get_temp_dir() . '/report_test_' . uniqid();
        mkdir($this->testDataDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testDataDir)) {
            $this->removeDirectory($this->testDataDir);
        }
    }

    public function testCompleteWorkflowConsoleReport(): void
    {
        // Create test PHP file with various issues
        file_put_contents($this->testDataDir . '/User.php', <<<'PHP'
<?php
class User {
    private $name;
    
    public function setName($name = null) {
        $this->name = $name;
    }
    
    public function getName() {
        return $this->name;
    }
}
PHP
        );

        // Scan the directory
        $scanResult = $this->scanner->scanDirectory($this->testDataDir);

        // Generate console report
        $consoleReport = $this->reportGenerator->generateConsoleReport($scanResult);

        // Verify report contains expected information
        $this->assertStringContainsString('PHP 8.4 Modernization Scan Report', $consoleReport);
        $this->assertStringContainsString('Files Scanned: 1', $consoleReport);
        $this->assertStringContainsString('User.php', $consoleReport);
    }

    public function testCompleteWorkflowJsonReport(): void
    {
        // Create test PHP file
        file_put_contents($this->testDataDir . '/Calculator.php', <<<'PHP'
<?php
class Calculator {
    public function add($a, $b) {
        return $a + $b;
    }
}
PHP
        );

        // Scan and generate JSON report
        $scanResult = $this->scanner->scanDirectory($this->testDataDir);
        $jsonReport = $this->reportGenerator->generateJsonReport($scanResult);

        // Verify JSON structure
        $data = json_decode($jsonReport, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('issues', $data);
        $this->assertEquals(1, $data['summary']['filesScanned']);
    }

    public function testCompleteWorkflowHtmlReport(): void
    {
        // Create test PHP file
        file_put_contents($this->testDataDir . '/Helper.php', <<<'PHP'
<?php
function formatDate($date = null) {
    return $date ? $date->format('Y-m-d') : null;
}
PHP
        );

        // Scan and generate HTML report
        $scanResult = $this->scanner->scanDirectory($this->testDataDir);
        $htmlReport = $this->reportGenerator->generateHtmlReport($scanResult);

        // Verify HTML structure
        $this->assertStringContainsString('<!DOCTYPE html>', $htmlReport);
        $this->assertStringContainsString('PHP 8.4 Modernization Scan Report', $htmlReport);
        $this->assertStringContainsString('Helper.php', $htmlReport);
    }

    public function testMultipleFilesGroupedByDirectory(): void
    {
        // Create nested directory structure
        $srcDir = $this->testDataDir . '/src';
        $libDir = $this->testDataDir . '/lib';
        mkdir($srcDir, 0777, true);
        mkdir($libDir, 0777, true);

        file_put_contents($srcDir . '/User.php', '<?php function getUser($id = null) {}');
        file_put_contents($libDir . '/Helper.php', '<?php function help($msg = null) {}');

        // Scan and generate reports
        $scanResult = $this->scanner->scanDirectory($this->testDataDir);
        $consoleReport = $this->reportGenerator->generateConsoleReport($scanResult);
        $jsonReport = $this->reportGenerator->generateJsonReport($scanResult);

        // Verify grouping in console report
        $this->assertStringContainsString($srcDir, $consoleReport);
        $this->assertStringContainsString($libDir, $consoleReport);

        // Verify grouping in JSON report
        $data = json_decode($jsonReport, true);
        $this->assertArrayHasKey($srcDir, $data['byDirectory']);
        $this->assertArrayHasKey($libDir, $data['byDirectory']);
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
