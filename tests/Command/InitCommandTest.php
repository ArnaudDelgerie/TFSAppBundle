<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class InitCommandTest extends TestCase
{
    private string $baseDir;

    private string $projectRoot;

    private ?InitCommandTestKernel $kernel = null;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/tfs-app-bundle-init-tests/' . uniqid('t-', true);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        restore_exception_handler();
        self::removeDir($this->baseDir);
    }

    public function testAcceptingDefaultsCreatesConfigWithDerivedValues(): void
    {
        $tester = $this->createTester('Demo Project');

        $tester->setInputs(['', '', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        self::assertSame([
            'project_name' => 'demo-project',
            'product_name' => 'Demo Project',
            'identifier' => 'dev.local.demo-project',
            'app_version' => '0.1.0',
        ], $this->readConfig());
    }

    public function testCustomAnswersLandVerbatimInFile(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['custom_app', 'Custom App Name', 'com.example.customapp', '2.3.4', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        self::assertSame([
            'project_name' => 'custom_app',
            'product_name' => 'Custom App Name',
            'identifier' => 'com.example.customapp',
            'app_version' => '2.3.4',
        ], $this->readConfig());
    }

    public function testInvalidProjectNameAndAppVersionAreReaskedThenAccepted(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['bad name', 'goodname', '', '', '01.2.3', '1.2.3', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        self::assertSame([
            'project_name' => 'goodname',
            'product_name' => 'Goodname',
            'identifier' => 'dev.local.goodname',
            'app_version' => '1.2.3',
        ], $this->readConfig());
    }

    public function testAsyncWorkerYesAddsKey(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', 'yes', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(true, $this->readConfig()['async_worker']);
    }

    public function testAsyncWorkerNoOmitsKey(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', 'no', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertArrayNotHasKey('async_worker', $this->readConfig());
    }

    public function testReleasesRepoProvidedIsWritten(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '', 'myorg/myapp']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('myorg/myapp', $this->readConfig()['releases_repo']);
    }

    public function testReleasesRepoEmptyOmitsKey(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertArrayNotHasKey('releases_repo', $this->readConfig());
    }

    public function testReleasesRepoMalformedIsReaskedThenAccepted(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '', 'bad repo!', 'myorg/myapp']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('myorg/myapp', $this->readConfig()['releases_repo']);
    }

    public function testExistingFileIsLeftUntouchedAndNoPromptsAreShown(): void
    {
        $tester = $this->createTester('project');

        $configPath = $this->projectRoot . '/tfsapp.config.json';
        $original = json_encode(['project_name' => 'already-there'], \JSON_PRETTY_PRINT) . \PHP_EOL;
        file_put_contents($configPath, $original);

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringNotContainsString('project_name', $tester->getDisplay());
        self::assertSame($original, file_get_contents($configPath));
    }

    private function createTester(string $rootBasename): CommandTester
    {
        $this->projectRoot = $this->baseDir . '/' . $rootBasename;
        mkdir($this->projectRoot, 0777, true);

        $this->kernel = new InitCommandTestKernel($this->projectRoot);
        $this->kernel->boot();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        return new CommandTester($application->find('tfsapp:init'));
    }

    private function readConfig(): array
    {
        $configPath = $this->projectRoot . '/tfsapp.config.json';

        self::assertFileExists($configPath);

        return json_decode(file_get_contents($configPath), true, flags: \JSON_THROW_ON_ERROR);
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new \FilesystemIterator($dir) as $item) {
            if ($item->isDir()) {
                self::removeDir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
