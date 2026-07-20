<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Kernel;

use PHPUnit\Framework\TestCase;

final class TFSAppKernelTest extends TestCase
{
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testDirsAreRelocatedWhenEnvVarsAreSet(): void
    {
        $cacheDir = sys_get_temp_dir() . '/tfs-app-bundle-relocated/cache';
        $buildDir = sys_get_temp_dir() . '/tfs-app-bundle-relocated/build';
        $logDir = sys_get_temp_dir() . '/tfs-app-bundle-relocated/log';

        $_SERVER['APP_CACHE_DIR'] = $cacheDir;
        $_SERVER['APP_BUILD_DIR'] = $buildDir;
        $_SERVER['APP_LOG_DIR'] = $logDir;

        $kernel = new RelocatedTestKernel('test', false);

        self::assertSame($cacheDir, $kernel->getCacheDir());
        self::assertSame($buildDir, $kernel->getBuildDir());
        self::assertSame($logDir, $kernel->getLogDir());

        $kernel->boot();

        self::assertDirectoryExists($cacheDir);

        $kernel->shutdown();
        restore_exception_handler();
    }

    public function testDirsFallBackToSymfonyDefaultsWhenEnvVarsAreAbsent(): void
    {
        unset($_SERVER['APP_CACHE_DIR'], $_SERVER['APP_BUILD_DIR'], $_SERVER['APP_LOG_DIR']);

        $kernel = new RelocatedTestKernel('test', false);

        self::assertSame($kernel->getProjectDir() . '/var/cache/test', $kernel->getCacheDir());
        self::assertSame($kernel->getProjectDir() . '/var/cache/test', $kernel->getBuildDir());
        self::assertSame($kernel->getProjectDir() . '/var/log', $kernel->getLogDir());
    }
}
