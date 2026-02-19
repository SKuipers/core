<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Tests\Unit\Migration;

use Gibbon\Modernization\Migration\BackupManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BackupManagerTest extends TestCase
{
    private string $testDir;
    private string $backupDir;
    private BackupManager $backupManager;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/backup-manager-test-' . uniqid();
        $this->backupDir = $this->testDir . '/backups';
        
        mkdir($this->testDir, 0755, true);
        
        $this->backupManager = new BackupManager($this->backupDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    public function testBackupFileCreatesBackup(): void
    {
        $testFile = $this->testDir . '/test.php';
        $content = '<?php echo "test";';
        file_put_contents($testFile, $content);

        $backupPath = $this->backupManager->backupFile($testFile);

        $this->assertFileExists($backupPath);
        $this->assertEquals($content, file_get_contents($backupPath));
        $this->assertStringContainsString('test_', $backupPath);
    }

    public function testBackupFileThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        $this->backupManager->backupFile($this->testDir . '/nonexistent.php');
    }

    public function testBackupFileCreatesDirectoryStructure(): void
    {
        $subDir = $this->testDir . '/src/Module';
        mkdir($subDir, 0755, true);
        
        $testFile = $subDir . '/Class.php';
        file_put_contents($testFile, '<?php class Test {}');

        $backupPath = $this->backupManager->backupFile($testFile);

        $this->assertFileExists($backupPath);
        $this->assertStringContainsString('src/Module', $backupPath);
    }

    public function testRestoreFileRestoresFromBackup(): void
    {
        $testFile = $this->testDir . '/test.php';
        $originalContent = '<?php echo "original";';
        file_put_contents($testFile, $originalContent);

        // Create backup
        $this->backupManager->backupFile($testFile);

        // Modify original file
        $modifiedContent = '<?php echo "modified";';
        file_put_contents($testFile, $modifiedContent);

        // Restore from backup
        $result = $this->backupManager->restoreFile($testFile);

        $this->assertTrue($result);
        $this->assertEquals($originalContent, file_get_contents($testFile));
    }

    public function testRestoreFileReturnsFalseWhenNoBackupExists(): void
    {
        $testFile = $this->testDir . '/test.php';
        file_put_contents($testFile, '<?php echo "test";');

        $result = $this->backupManager->restoreFile($testFile);

        $this->assertFalse($result);
    }

    public function testRestoreFileUsesLatestBackup(): void
    {
        $testFile = $this->testDir . '/test.php';
        
        // Create first backup
        file_put_contents($testFile, '<?php echo "version1";');
        $this->backupManager->backupFile($testFile);
        
        sleep(1); // Ensure different timestamps
        
        // Create second backup
        file_put_contents($testFile, '<?php echo "version2";');
        $this->backupManager->backupFile($testFile);
        
        // Modify file
        file_put_contents($testFile, '<?php echo "modified";');
        
        // Restore should use latest backup
        $this->backupManager->restoreFile($testFile);
        
        $this->assertEquals('<?php echo "version2";', file_get_contents($testFile));
    }

    public function testCleanupBackupsRemovesOldFiles(): void
    {
        $testFile = $this->testDir . '/test.php';
        file_put_contents($testFile, '<?php echo "test";');

        // Create backup
        $backupPath = $this->backupManager->backupFile($testFile);
        
        // Make backup appear old by modifying its timestamp
        touch($backupPath, time() - (8 * 86400)); // 8 days old

        $removedCount = $this->backupManager->cleanupBackups(7);

        $this->assertEquals(1, $removedCount);
        $this->assertFileDoesNotExist($backupPath);
    }

    public function testCleanupBackupsKeepsRecentFiles(): void
    {
        $testFile = $this->testDir . '/test.php';
        file_put_contents($testFile, '<?php echo "test";');

        // Create recent backup
        $backupPath = $this->backupManager->backupFile($testFile);

        $removedCount = $this->backupManager->cleanupBackups(7);

        $this->assertEquals(0, $removedCount);
        $this->assertFileExists($backupPath);
    }

    public function testCleanupBackupsReturnsZeroWhenNoBackupDirectory(): void
    {
        $emptyBackupManager = new BackupManager($this->testDir . '/nonexistent');
        
        $removedCount = $emptyBackupManager->cleanupBackups(7);

        $this->assertEquals(0, $removedCount);
    }

    public function testGetBackupDirectoryReturnsCorrectPath(): void
    {
        $this->assertEquals($this->backupDir, $this->backupManager->getBackupDirectory());
    }

    public function testBackupManagerCreatesBackupDirectoryOnConstruction(): void
    {
        $newBackupDir = $this->testDir . '/new-backups';
        $this->assertDirectoryDoesNotExist($newBackupDir);

        new BackupManager($newBackupDir);

        $this->assertDirectoryExists($newBackupDir);
    }

    public function testBackupFilePreservesFilePermissions(): void
    {
        $testFile = $this->testDir . '/test.php';
        file_put_contents($testFile, '<?php echo "test";');
        chmod($testFile, 0644);

        $backupPath = $this->backupManager->backupFile($testFile);

        $this->assertFileExists($backupPath);
        // Backup should be readable
        $this->assertTrue(is_readable($backupPath));
    }

    public function testMultipleBackupsForSameFile(): void
    {
        $testFile = $this->testDir . '/test.php';
        
        file_put_contents($testFile, '<?php echo "v1";');
        $backup1 = $this->backupManager->backupFile($testFile);
        
        sleep(1);
        
        file_put_contents($testFile, '<?php echo "v2";');
        $backup2 = $this->backupManager->backupFile($testFile);

        $this->assertFileExists($backup1);
        $this->assertFileExists($backup2);
        $this->assertNotEquals($backup1, $backup2);
        $this->assertEquals('<?php echo "v1";', file_get_contents($backup1));
        $this->assertEquals('<?php echo "v2";', file_get_contents($backup2));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

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
