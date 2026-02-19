<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Migration;

use RuntimeException;

/**
 * Manages file backups during migration process.
 * 
 * Creates backups before modifying files, supports restoration,
 * and provides cleanup functionality for old backups.
 */
class BackupManager
{
    private string $backupDir;

    public function __construct(?string $backupDir = null)
    {
        $this->backupDir = $backupDir ?? sys_get_temp_dir() . '/php-modernization-backups';
        $this->ensureBackupDirectoryExists();
    }

    /**
     * Create a backup of the specified file.
     * 
     * @param string $filePath Path to the file to backup
     * @return string Path to the backup file
     * @throws RuntimeException If backup fails
     */
    public function backupFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException("File not readable: {$filePath}");
        }

        $timestamp = date('Y-m-d_His');
        $backupPath = $this->generateBackupPath($filePath, $timestamp);
        
        $backupFileDir = dirname($backupPath);
        if (!is_dir($backupFileDir)) {
            if (!mkdir($backupFileDir, 0755, true)) {
                throw new RuntimeException("Failed to create backup directory: {$backupFileDir}");
            }
        }

        if (!copy($filePath, $backupPath)) {
            throw new RuntimeException("Failed to backup file: {$filePath}");
        }

        return $backupPath;
    }

    /**
     * Restore a file from its backup.
     * 
     * @param string $filePath Path to the original file
     * @return bool True if restoration was successful
     */
    public function restoreFile(string $filePath): bool
    {
        $backupPath = $this->findLatestBackup($filePath);
        
        if ($backupPath === null) {
            return false;
        }

        if (!file_exists($backupPath)) {
            return false;
        }

        return copy($backupPath, $filePath);
    }

    /**
     * Clean up old backup files.
     * 
     * @param int $olderThanDays Remove backups older than this many days
     * @return int Number of backups removed
     */
    public function cleanupBackups(int $olderThanDays = 7): int
    {
        if (!is_dir($this->backupDir)) {
            return 0;
        }

        $cutoffTime = time() - ($olderThanDays * 86400);
        $removedCount = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->backupDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoffTime) {
                if (unlink($file->getPathname())) {
                    $removedCount++;
                }
            }
        }

        // Remove empty directories
        $this->removeEmptyDirectories($this->backupDir);

        return $removedCount;
    }

    /**
     * Get the backup directory path.
     */
    public function getBackupDirectory(): string
    {
        return $this->backupDir;
    }

    /**
     * Ensure the backup directory exists.
     */
    private function ensureBackupDirectoryExists(): void
    {
        if (!is_dir($this->backupDir)) {
            if (!mkdir($this->backupDir, 0755, true)) {
                throw new RuntimeException("Failed to create backup directory: {$this->backupDir}");
            }
        }
    }

    /**
     * Generate a backup file path with timestamp.
     */
    private function generateBackupPath(string $filePath, string $timestamp): string
    {
        $relativePath = $this->getRelativePath($filePath);
        $pathInfo = pathinfo($relativePath);
        
        $directory = $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] : '';
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';
        
        $backupFilename = $filename . '_' . $timestamp . ($extension ? '.' . $extension : '');
        
        return $this->backupDir . '/' . ($directory ? $directory . '/' : '') . $backupFilename;
    }

    /**
     * Find the latest backup for a given file.
     */
    private function findLatestBackup(string $filePath): ?string
    {
        $relativePath = $this->getRelativePath($filePath);
        $pathInfo = pathinfo($relativePath);
        
        $directory = $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] : '';
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';
        
        $searchDir = $this->backupDir . '/' . ($directory ? $directory . '/' : '');
        
        if (!is_dir($searchDir)) {
            return null;
        }

        $pattern = $filename . '_*' . ($extension ? '.' . $extension : '');
        $backups = glob($searchDir . $pattern);
        
        if (empty($backups)) {
            return null;
        }

        // Sort by modification time, newest first
        usort($backups, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $backups[0];
    }

    /**
     * Get relative path from absolute path.
     */
    private function getRelativePath(string $filePath): string
    {
        // Normalize paths
        $filePath = realpath($filePath);
        if ($filePath === false) {
            return basename($filePath);
        }
        
        $cwd = getcwd();
        if ($cwd !== false) {
            $cwd = realpath($cwd);
            if ($cwd !== false && str_starts_with($filePath, $cwd)) {
                return substr($filePath, strlen($cwd) + 1);
            }
        }
        
        return $filePath;
    }

    /**
     * Remove empty directories recursively.
     */
    private function removeEmptyDirectories(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath)) {
                $this->removeEmptyDirectories($fullPath);
            }
        }

        // Try to remove directory if it's empty
        $items = scandir($path);
        if ($items !== false && count($items) === 2) { // Only . and ..
            @rmdir($path);
        }
    }
}
