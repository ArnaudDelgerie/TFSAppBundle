<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
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

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        self::assertSame([
            'project_name' => 'demo-project',
            'product_name' => 'Demo Project',
            'identifier' => 'dev.local.demo-project',
            'app_version' => '0.1.0',
            'actions' => self::actionsSkeleton(),
        ], $this->readConfig());
    }

    public function testCustomAnswersLandVerbatimInFile(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['custom_app', 'Custom App Name', 'com.example.customapp', '2.3.4', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        self::assertSame([
            'project_name' => 'custom_app',
            'product_name' => 'Custom App Name',
            'identifier' => 'com.example.customapp',
            'app_version' => '2.3.4',
            'actions' => self::actionsSkeleton(),
        ], $this->readConfig());
    }

    public function testInvalidProjectNameAndAppVersionAreReaskedThenAccepted(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['bad name', 'goodname', '', '', '01.2.3', '1.2.3', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        self::assertSame([
            'project_name' => 'goodname',
            'product_name' => 'Goodname',
            'identifier' => 'dev.local.goodname',
            'app_version' => '1.2.3',
            'actions' => self::actionsSkeleton(),
        ], $this->readConfig());
    }

    public function testWorkersYesWritesOneDeclaration(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', 'yes']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $config = $this->readConfig();

        self::assertSame([['transports' => ['async']]], $config['workers']);
        self::assertArrayNotHasKey('async_worker', $config);
        self::assertSame(
            ['project_name', 'product_name', 'identifier', 'app_version', 'workers', 'actions'],
            array_keys($config),
        );
    }

    public function testWorkersNoOmitsKey(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', 'no']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $config = $this->readConfig();

        self::assertArrayNotHasKey('workers', $config);
        self::assertArrayNotHasKey('async_worker', $config);
    }

    public function testNoObsoleteKeysAreWritten(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $config = $this->readConfig();

        self::assertArrayNotHasKey('releases_repo', $config);
        self::assertStringNotContainsString('pre-build', (string) file_get_contents($this->projectRoot . '/tfsapp.config.json'));
        self::assertStringNotContainsString('post-build', (string) file_get_contents($this->projectRoot . '/tfsapp.config.json'));
    }

    public function testCommandsScaffoldOmitsMigrationHooksWithoutDoctrine(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertArrayNotHasKey('commands', $this->readConfig());
    }

    public function testCommandsScaffoldAddsMigrationHooksWithDoctrineInstalled(): void
    {
        $tester = $this->createTester('project');
        $this->writeComposerLock(['doctrine/doctrine-migrations-bundle']);

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(self::buildCommands(withMigrations: true), $this->readConfig()['commands']);
    }

    public function testCommandsScaffoldOmitsMigrationHooksWithMalformedComposerLock(): void
    {
        $tester = $this->createTester('project');
        file_put_contents($this->projectRoot . '/composer.lock', '{not valid json');

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertArrayNotHasKey('commands', $this->readConfig());
    }

    public function testCommandsScaffoldAddsMigrationHooksWhenDoctrineInPackagesDev(): void
    {
        $tester = $this->createTester('project');
        $this->writeComposerLock([], ['doctrine/doctrine-migrations-bundle']);

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(self::buildCommands(withMigrations: true), $this->readConfig()['commands']);
    }

    public function testActionsSkeletonIsWrittenAllFalse(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $config = $this->readConfig();

        self::assertSame(self::actionsSkeleton(), $config['actions']);
        self::assertSame(
            ['secrets', 'update', 'picker', 'close_guard', 'open_files'],
            array_keys($config['actions']),
        );
        self::assertArrayNotHasKey('bridge', $config['actions']['picker']);
        self::assertArrayNotHasKey('bridge', $config['actions']['open_files']);
        self::assertArrayNotHasKey('file_associations', $config);
        foreach ($config['actions'] as $group) {
            self::assertNotContains(true, $group, 'every declared member must default to false');
        }
    }

    public function testGeneratedConfigDecodesWithNoUnsupportedTransportOrDirectoryMimePairing(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        $config = json_decode(
            (string) file_get_contents($this->projectRoot . '/tfsapp.config.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        $ipcOnlyGroups = ['picker', 'open_files'];

        foreach ($ipcOnlyGroups as $group) {
            self::assertArrayNotHasKey('bridge', $config['actions'][$group], sprintf('%s has no bridge transport', $group));
        }

        self::assertArrayNotHasKey('directories', $config['actions']['secrets']);
        self::assertArrayNotHasKey('keys', $config['actions']['update']);

        // A receiver that has not opted in must not claim desktop MIME support.
        self::assertArrayNotHasKey('file_associations', $config);
    }

    public function testExistingFileIsLeftUntouchedAndCommandStillRunsToCompletion(): void
    {
        $tester = $this->createTester('project');

        $configPath = $this->projectRoot . '/tfsapp.config.json';
        $original = json_encode(['project_name' => 'already-there'], \JSON_PRETTY_PRINT) . \PHP_EOL;
        file_put_contents($configPath, $original);

        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringNotContainsString('project_name', $tester->getDisplay());
        self::assertSame($original, file_get_contents($configPath));
        self::assertDirectoryDoesNotExist($this->projectRoot . '/tfsapp_build');
        self::assertFileDoesNotExist($this->projectRoot . '/.gitignore');
    }

    public function testNoBuildSurfaceIsCreatedOnFreshProject(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertDirectoryDoesNotExist($this->projectRoot . '/tfsapp_build');
        self::assertFileDoesNotExist($this->projectRoot . '/.gitignore');
    }

    public function testExistingBuildDirAndGitignoreEntryAreLeftAlone(): void
    {
        $tester = $this->createTester('project');

        mkdir($this->projectRoot . '/tfsapp_build');
        file_put_contents($this->projectRoot . '/tfsapp_build/.gitkeep', '');
        $gitignorePath = $this->projectRoot . '/.gitignore';
        $originalGitignore = '/vendor/' . \PHP_EOL . '/tfsapp_build/' . \PHP_EOL;
        file_put_contents($gitignorePath, $originalGitignore);

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame($originalGitignore, file_get_contents($gitignorePath));
        self::assertFileExists($this->projectRoot . '/tfsapp_build/.gitkeep');
    }

    public function testRerunOverExistingFilesCreatesNoBuildSurface(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $secondTester = new CommandTester((new Application($this->kernel))->find('tfsapp:init'));
        $secondTester->execute([]);

        self::assertSame(Command::SUCCESS, $secondTester->getStatusCode());
        self::assertDirectoryDoesNotExist($this->projectRoot . '/tfsapp_build');
        self::assertFileDoesNotExist($this->projectRoot . '/.gitignore');
    }

    public function testChangelogIsCreatedWithConfiguredAppVersion(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '2.5.0', '']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $changelogPath = $this->projectRoot . '/CHANGELOG.md';
        self::assertFileExists($changelogPath);
        self::assertSame(
            '# Changelog' . \PHP_EOL . \PHP_EOL . '## 2.5.0' . \PHP_EOL . \PHP_EOL . '- Initial release.' . \PHP_EOL,
            file_get_contents($changelogPath),
        );
    }

    public function testExistingChangelogIsLeftUntouched(): void
    {
        $tester = $this->createTester('project');

        $changelogPath = $this->projectRoot . '/CHANGELOG.md';
        $original = '# Changelog' . \PHP_EOL . \PHP_EOL . '## 9.9.9' . \PHP_EOL . \PHP_EOL . '- Something else.' . \PHP_EOL;
        file_put_contents($changelogPath, $original);

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame($original, file_get_contents($changelogPath));
    }

    public function testSecondRunLeavesChangelogUntouched(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $changelogPath = $this->projectRoot . '/CHANGELOG.md';
        $afterFirstRun = file_get_contents($changelogPath);

        $secondTester = new CommandTester((new Application($this->kernel))->find('tfsapp:init'));
        $secondTester->execute([]);

        self::assertSame(Command::SUCCESS, $secondTester->getStatusCode());
        self::assertSame($afterFirstRun, file_get_contents($changelogPath));
    }

    public function testReadmeIsCreatedWithIdentityHeaderAndHubCommandsSection(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $readmePath = $this->projectRoot . '/TFSAPP_README.md';
        self::assertFileExists($readmePath);

        $contents = file_get_contents($readmePath);

        self::assertStringContainsString('# Project' . \PHP_EOL, $contents);
        self::assertStringContainsString('`dev.local.project`', $contents);
        self::assertStringContainsString('installed and run by TFSAppHub', $contents);
        self::assertStringContainsString('TFSAppHub\'s `CONTRACT.md`', $contents);
        self::assertStringContainsString('## Hub commands' . \PHP_EOL, $contents);
        self::assertStringContainsString('`tfsapp-hub list`', $contents);
        self::assertStringContainsString('- `tfsapp-hub open <id>`', $contents);
        self::assertStringContainsString('`tfsapp-hub run <id> <alias> [args...]`', $contents);
        self::assertStringContainsString('- `tfsapp-hub update <id> [<archive.tar.gz>] [--ref <tag>] [--yes]`', $contents);
        self::assertStringContainsString('- `tfsapp-hub rollback <id> [--yes]`', $contents);
        self::assertStringContainsString('- `tfsapp-hub export <id> <path>`', $contents);
        self::assertStringContainsString('- `tfsapp-hub import <id> <path> [--force] [--yes]`', $contents);
        self::assertStringContainsString('- `tfsapp-hub remove <id> [--purge] [--yes]`', $contents);
        self::assertStringContainsString('`tfsapp-hub --help` is the authoritative one', $contents);
        self::assertStringNotContainsString('AppImage', $contents);
        self::assertStringContainsString('## Optional config fields' . \PHP_EOL, $contents);
        self::assertStringContainsString('`actions`', $contents);
        self::assertStringContainsString('`run`', $contents);
        self::assertStringContainsString('`app_port`', $contents);
        self::assertStringContainsString('`icon_path`', $contents);
        self::assertStringContainsString('`splash_*`', $contents);
        self::assertStringContainsString('`workers` declaration shape', $contents);
        self::assertStringContainsString('TFSAppHub\'s `CONTRACT.md` §2', $contents);
        self::assertStringContainsString('written once by `tfsapp:init` and is never refreshed automatically', $contents);
    }

    public function testExistingReadmeIsLeftUntouched(): void
    {
        $tester = $this->createTester('project');

        $readmePath = $this->projectRoot . '/TFSAPP_README.md';
        $original = '# Something else' . \PHP_EOL;
        file_put_contents($readmePath, $original);

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame($original, file_get_contents($readmePath));
    }

    public function testSecondRunLeavesReadmeUntouched(): void
    {
        $tester = $this->createTester('project');

        $tester->setInputs(['', '', '', '', '']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $readmePath = $this->projectRoot . '/TFSAPP_README.md';
        $afterFirstRun = file_get_contents($readmePath);

        $secondTester = new CommandTester((new Application($this->kernel))->find('tfsapp:init'));
        $secondTester->execute([]);

        self::assertSame(Command::SUCCESS, $secondTester->getStatusCode());
        self::assertSame($afterFirstRun, file_get_contents($readmePath));
    }

    public function testChangelogIsSkippedWithWarningWhenConfigIsMalformed(): void
    {
        $tester = $this->createTester('project');

        $configPath = $this->projectRoot . '/tfsapp.config.json';
        file_put_contents($configPath, '{not valid json');

        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileDoesNotExist($this->projectRoot . '/CHANGELOG.md');
        self::assertStringContainsString('Could not resolve app_version', $tester->getDisplay());
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

    private function writeComposerLock(array $packages = [], array $packagesDev = []): void
    {
        $lock = [
            'packages' => array_map(static fn (string $name): array => ['name' => $name], $packages),
            'packages-dev' => array_map(static fn (string $name): array => ['name' => $name], $packagesDev),
        ];

        file_put_contents($this->projectRoot . '/composer.lock', json_encode($lock, \JSON_PRETTY_PRINT) . \PHP_EOL);
    }

    private static function buildCommands(bool $withMigrations = false): array
    {
        if (!$withMigrations) {
            return [];
        }

        return [
            'pre-install' => ['doctrine:migrations:migrate --no-interaction'],
            'pre-update' => ['doctrine:migrations:migrate --no-interaction'],
        ];
    }

    private static function actionsSkeleton(): array
    {
        return [
            'secrets' => [
                'ipc' => false,
                'bridge' => false,
                'keys' => [],
            ],
            'update' => [
                'ipc' => false,
                'bridge' => false,
            ],
            'picker' => [
                'ipc' => false,
            ],
            'close_guard' => [
                'ipc' => false,
                'bridge' => false,
            ],
            'open_files' => [
                'ipc' => false,
                'directories' => false,
            ],
        ];
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
