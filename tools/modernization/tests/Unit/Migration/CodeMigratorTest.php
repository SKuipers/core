<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\Migration;

use Gibbon\Modernization\Config\MigrationConfig;
use Gibbon\Modernization\Migration\BackupManager;
use Gibbon\Modernization\Migration\CodeMigrator;
use Gibbon\Modernization\Migration\CodeTransformer;
use Gibbon\Modernization\TypeInference\InferredType;
use Gibbon\Modernization\TypeInference\InheritanceAnalyzer;
use Gibbon\Modernization\TypeInference\PHPDocAnalyzer;
use Gibbon\Modernization\TypeInference\TypeInferenceEngine;
use Gibbon\Modernization\TypeInference\UsageAnalyzer;
use PHPUnit\Framework\TestCase;

class CodeMigratorTest extends TestCase
{
    private CodeMigrator $migrator;
    private string $tempDir;
    private MigrationConfig $config;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/migrator-test-' . uniqid();
        mkdir($this->tempDir);

        $this->config = new MigrationConfig(
            rootPath: $this->tempDir,
            includePaths: ['src/'],
            excludePaths: ['vendor/'],
            dryRun: false,
            createBackups: true,
            confidenceThreshold: 0.7
        );

        $phpDocAnalyzer = new PHPDocAnalyzer();
        $usageAnalyzer = new UsageAnalyzer();
        $inheritanceAnalyzer = new InheritanceAnalyzer();
        $typeInference = new TypeInferenceEngine($phpDocAnalyzer, $usageAnalyzer, $inheritanceAnalyzer);
        $transformer = new CodeTransformer();
        $backupManager = new BackupManager($this->tempDir . '/backups');

        $this->migrator = new CodeMigrator($typeInference, $transformer, $backupManager, $this->config);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testMigrateFileWithImplicitlyNullableParameter(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    /**
     * @param string $name
     */
    public function setName($name = null)
    {
        $this->name = $name;
    }
}
PHP;

        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $code);

        $result = $this->migrator->migrateFile($filePath);

        $this->assertTrue($result->success);
        $this->assertTrue($result->modified);
        $this->assertGreaterThan(0, $result->parametersUpdated);
        $this->assertNotNull($result->backupPath);
    }

    public function testMigrateFileWithMissingReturnType(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
PHP;

        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $code);

        $result = $this->migrator->migrateFile($filePath);

        $this->assertTrue($result->success);
        $this->assertTrue($result->modified);
        $this->assertGreaterThan(0, $result->returnTypesAdded);
    }

    public function testMigrateFileInDryRunMode(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    /**
     * @param string $name
     */
    public function setName($name = null)
    {
        $this->name = $name;
    }
}
PHP;

        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $code);

        $dryRunConfig = new MigrationConfig(
            rootPath: $this->tempDir,
            dryRun: true,
            createBackups: false
        );

        $phpDocAnalyzer = new PHPDocAnalyzer();
        $usageAnalyzer = new UsageAnalyzer();
        $inheritanceAnalyzer = new InheritanceAnalyzer();
        $typeInference = new TypeInferenceEngine($phpDocAnalyzer, $usageAnalyzer, $inheritanceAnalyzer);
        $transformer = new CodeTransformer();
        $backupManager = new BackupManager($this->tempDir . '/backups');

        $dryRunMigrator = new CodeMigrator($typeInference, $transformer, $backupManager, $dryRunConfig);
        $result = $dryRunMigrator->migrateFile($filePath);

        $this->assertTrue($result->success);
        $this->assertTrue($result->modified);
        $this->assertNotNull($result->modifiedCode);
        $this->assertNull($result->backupPath);
        
        // File should not be modified
        $this->assertEquals($code, file_get_contents($filePath));
    }

    public function testMigrateFileWithNonExistentFile(): void
    {
        $result = $this->migrator->migrateFile($this->tempDir . '/nonexistent.php');

        $this->assertFalse($result->success);
        $this->assertFalse($result->modified);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('not found', $result->error);
    }

    public function testMigrateFileWithInvalidPhp(): void
    {
        $code = '<?php this is not valid php';
        $filePath = $this->tempDir . '/invalid.php';
        file_put_contents($filePath, $code);

        $result = $this->migrator->migrateFile($filePath);

        $this->assertFalse($result->success);
        $this->assertFalse($result->modified);
        $this->assertNotNull($result->error);
    }

    public function testMigrateDirectory(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir);

        $code1 = <<<'PHP'
<?php

class TestClass1
{
    /**
     * @param string $name
     */
    public function setName($name = null)
    {
        $this->name = $name;
    }
}
PHP;

        $code2 = <<<'PHP'
<?php

class TestClass2
{
    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
PHP;

        file_put_contents($srcDir . '/test1.php', $code1);
        file_put_contents($srcDir . '/test2.php', $code2);

        $result = $this->migrator->migrateDirectory($srcDir);

        $this->assertEquals(2, $result->filesProcessed);
        $this->assertGreaterThan(0, $result->filesModified);
        $this->assertGreaterThan(0, $result->getTotalTypeHintsAdded());
    }

    public function testMigrateDirectoryWithExcludedFiles(): void
    {
        $srcDir = $this->tempDir . '/src';
        $vendorDir = $this->tempDir . '/src/vendor';
        mkdir($srcDir);
        mkdir($vendorDir);

        $code = <<<'PHP'
<?php

class TestClass
{
    /**
     * @param string $name
     */
    public function setName($name = null)
    {
        $this->name = $name;
    }
}
PHP;

        file_put_contents($srcDir . '/test.php', $code);
        file_put_contents($vendorDir . '/excluded.php', $code);

        $result = $this->migrator->migrateDirectory($srcDir);

        // Should only process the non-excluded file
        $this->assertEquals(1, $result->filesProcessed);
    }

    public function testMigrateDirectoryWithBatchProcessing(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir);

        $code = <<<'PHP'
<?php

class TestClass
{
    /**
     * @param string $name
     */
    public function setName($name = null)
    {
        $this->name = $name;
    }
}
PHP;

        // Create multiple files
        for ($i = 1; $i <= 5; $i++) {
            file_put_contents($srcDir . "/test{$i}.php", $code);
        }

        $result = $this->migrator->migrateDirectory($srcDir, ['batchSize' => 2]);

        $this->assertEquals(5, $result->filesProcessed);
    }

    public function testDryRunOnFile(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    /**
     * @param string $name
     */
    public function setName($name = null)
    {
        $this->name = $name;
    }
}
PHP;

        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $code);

        $result = $this->migrator->dryRun($filePath);

        $this->assertEquals(1, $result->filesProcessed);
        $this->assertGreaterThan(0, $result->filesModified);
        
        // File should not be modified
        $this->assertEquals($code, file_get_contents($filePath));
    }

    public function testDryRunOnDirectory(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir);

        $code = <<<'PHP'
<?php

class TestClass
{
    /**
     * @param string $name
     */
    public function setName($name = null)
    {
        $this->name = $name;
    }
}
PHP;

        file_put_contents($srcDir . '/test1.php', $code);
        file_put_contents($srcDir . '/test2.php', $code);

        $result = $this->migrator->dryRun($srcDir);

        $this->assertEquals(2, $result->filesProcessed);
        $this->assertGreaterThan(0, $result->filesModified);
        
        // Files should not be modified
        $this->assertEquals($code, file_get_contents($srcDir . '/test1.php'));
        $this->assertEquals($code, file_get_contents($srcDir . '/test2.php'));
    }

    public function testFlaggedItemsForLowConfidence(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    // No PHPDoc, will have low confidence
    public function process($data)
    {
        return $data;
    }
}
PHP;

        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $code);

        $result = $this->migrator->migrateFile($filePath);

        $this->assertTrue($result->success);
        // May or may not be modified depending on confidence threshold
        // But should have flagged items if confidence is low
        if (!$result->modified) {
            $this->assertNotEmpty($result->flaggedItems);
        }
    }

    public function testMigrationResultToArray(): void
    {
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir);

        $code = <<<'PHP'
<?php

class TestClass
{
    /**
     * @param string $name
     */
    public function setName($name = null)
    {
        $this->name = $name;
    }
}
PHP;

        file_put_contents($srcDir . '/test.php', $code);

        $result = $this->migrator->migrateDirectory($srcDir);
        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('filesProcessed', $array);
        $this->assertArrayHasKey('filesModified', $array);
        $this->assertArrayHasKey('parametersUpdated', $array);
        $this->assertArrayHasKey('returnTypesAdded', $array);
        $this->assertArrayHasKey('propertyTypesAdded', $array);
        $this->assertArrayHasKey('totalTypeHintsAdded', $array);
        $this->assertArrayHasKey('flaggedForReview', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('successful', $array);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}

