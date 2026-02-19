<?php

namespace Gibbon\Modernization\Tests\Unit\Config;

use Gibbon\Modernization\Config\MigrationConfig;
use PHPUnit\Framework\TestCase;

class MigrationConfigTest extends TestCase
{
    public function testCanCreateConfigFromArray(): void
    {
        $config = MigrationConfig::fromArray([
            'rootPath' => '/test/path',
            'includePaths' => ['src/', 'lib/'],
            'excludePaths' => ['vendor/'],
            'dryRun' => true,
        ]);
        
        $this->assertSame('/test/path', $config->rootPath);
        $this->assertSame(['src/', 'lib/'], $config->includePaths);
        $this->assertSame(['vendor/'], $config->excludePaths);
        $this->assertTrue($config->dryRun);
    }
    
    public function testUsesDefaultValues(): void
    {
        $config = MigrationConfig::fromArray([
            'rootPath' => '/test/path',
        ]);
        
        $this->assertSame(['src/'], $config->includePaths);
        $this->assertSame(['vendor/', 'tests/'], $config->excludePaths);
        $this->assertFalse($config->dryRun);
        $this->assertTrue($config->createBackups);
        $this->assertSame(0.7, $config->confidenceThreshold);
    }
    
    public function testCanConvertToArray(): void
    {
        $config = new MigrationConfig(
            rootPath: '/test/path',
            includePaths: ['src/'],
            dryRun: true
        );
        
        $array = $config->toArray();
        
        $this->assertIsArray($array);
        $this->assertSame('/test/path', $array['rootPath']);
        $this->assertSame(['src/'], $array['includePaths']);
        $this->assertTrue($array['dryRun']);
    }
}
