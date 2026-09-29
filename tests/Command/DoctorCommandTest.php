<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class DoctorCommandTest extends TestCase
{
    private string $baseDir;

    private string $projectRoot;

    private ?DoctorCommandTestKernel $kernel = null;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/tfs-app-bundle-doctor-tests/' . uniqid('t-', true);
        $this->projectRoot = $this->baseDir . '/project';
        mkdir($this->projectRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        restore_exception_handler();

        putenv('TFS_APP_IDENTIFIER');
        putenv('TFS_APP_VERSION');
        putenv('TFS_ASYNC_WORKER');
        putenv('TFS_WORKER_TRANSPORTS');
        putenv('TFS_KEYRING_AVAILABLE');
        putenv('DATABASE_URL');
        unset($_SERVER['DATABASE_URL'], $_ENV['DATABASE_URL']);
        putenv('APP_UPLOAD_DIR');

        if (is_dir($this->baseDir . '/uploads')) {
            chmod($this->baseDir . '/uploads', 0777);
        }

        self::removeDir($this->baseDir);
    }

    public function testOutputCarriesHubContextAndAvailabilityLines(): void
    {
        putenv('TFS_APP_IDENTIFIER=dev.local.demo-project');
        putenv('TFS_APP_VERSION=1.2.3');
        putenv('TFS_ASYNC_WORKER=1');
        putenv('TFS_WORKER_TRANSPORTS=urgent,scheduled,background');
        putenv('TFS_KEYRING_AVAILABLE=1');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        $display = $tester->getDisplay();

        self::assertStringContainsString('1.2.3', $display);
        self::assertStringContainsString('keyring_available', $display);
        self::assertStringContainsString('async_worker', $display);
        self::assertStringContainsString('worker_transports', $display);
        self::assertStringContainsString('urgent, scheduled, background', $display);
        self::assertStringContainsString('bridge_enabled', $display);
    }

    public function testAlwaysExitsSuccessWithoutBridgeConfigured(): void
    {
        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('secrets_available', $tester->getDisplay());
        self::assertStringContainsString('update_check_available', $tester->getDisplay());
        self::assertMatchesRegularExpression('/update_check_available\s+no/', $tester->getDisplay());
        self::assertStringContainsString('worker_transports', $tester->getDisplay());
        self::assertStringContainsString('(none)', $tester->getDisplay());
    }

    public function testUpdateCheckAvailableRowMirrorsTheChecker(): void
    {
        $tester = $this->createTester(updateCheckerAvailable: true);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/update_check_available\s+yes/', $tester->getDisplay());
    }

    public function testUpdateCheckUnavailableRowMirrorsTheChecker(): void
    {
        $tester = $this->createTester(updateCheckerAvailable: false);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/update_check_available\s+no/', $tester->getDisplay());
    }

    public function testDefinedWorkerTransportsDoNotWarn(): void
    {
        putenv('TFS_WORKER_TRANSPORTS=urgent,background');

        $tester = $this->createTester(receiverTransports: ['urgent', 'background']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertDoesNotMatchRegularExpression('/does\s+not configure/', $tester->getDisplay());
    }

    public function testMissingWorkerTransportIsNamedInWarning(): void
    {
        putenv('TFS_WORKER_TRANSPORTS=urgent,background');

        $tester = $this->createTester(receiverTransports: ['urgent']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/does\s+not configure/', $tester->getDisplay());
        self::assertStringContainsString('background', $tester->getDisplay());
    }

    public function testMissingReceiverLocatorDoesNotWarn(): void
    {
        putenv('TFS_WORKER_TRANSPORTS=background');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertDoesNotMatchRegularExpression('/does\s+not configure/', $tester->getDisplay());
    }

    public function testDatabaseUrlNotSetIsReportedAsSuch(): void
    {
        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('database_url', $tester->getDisplay());
        self::assertStringContainsString('(not set)', $tester->getDisplay());
    }

    public function testNonSqliteDatabaseUrlIsPrintedResolvedWithoutFileChecks(): void
    {
        putenv('DATABASE_URL=postgresql://app:secret@127.0.0.1:5432/app?serverVersion=16');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertStringContainsString('postgresql://app:secret@127.0.0.1:5432/app', $display);
        self::assertStringNotContainsString('database_file_exists', $display);
    }

    public function testSqliteDatabaseUrlResolvesKernelProjectDirPlaceholder(): void
    {
        putenv('DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('sqlite:///' . $this->projectRoot . '/var/data.db', $tester->getDisplay());
    }

    public function testMissingSqliteFileIsFlaggedWithAWarning(): void
    {
        putenv('DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertStringContainsString('database_file_exists', $display);
        self::assertStringContainsString('no', $display);
        self::assertStringContainsString('does not exist', $display);
    }

    public function testExistingSqliteFileWithoutTablesIsFlaggedWithAWarning(): void
    {
        mkdir($this->projectRoot . '/var');
        $dbPath = $this->projectRoot . '/var/data.db';
        new \PDO('sqlite:' . $dbPath);

        putenv('DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertStringContainsString('database_tables', $display);
        self::assertStringContainsString('holds no tables', $display);
    }

    public function testExistingSqliteFileWithTablesReportsNoWarning(): void
    {
        mkdir($this->projectRoot . '/var');
        $dbPath = $this->projectRoot . '/var/data.db';
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');

        putenv('DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertStringContainsString('present', $display);
        self::assertStringNotContainsString('holds no tables', $display);
        self::assertStringNotContainsString('does not exist', $display);
    }

    public function testFreshSqliteFileReportsJournalModeAndBusyTimeout(): void
    {
        mkdir($this->projectRoot . '/var');
        $dbPath = $this->projectRoot . '/var/data.db';
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');

        putenv('DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertStringContainsString('sqlite_journal_mode', $display);
        self::assertStringContainsString('wal', $display);
        self::assertStringContainsString('sqlite_busy_timeout_ms', $display);
        self::assertStringContainsString('5000', $display);
        self::assertStringNotContainsString('not WAL', $display);
    }

    public function testSqliteFileNotYetInWalModeIsFlaggedWithAWarning(): void
    {
        mkdir($this->projectRoot . '/var');
        $dbPath = $this->projectRoot . '/var/data.db';
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');

        putenv('DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertStringContainsString('sqlite_journal_mode', $display);
        self::assertStringContainsString('not WAL', $display);
    }

    public function testSqlitePragmasDisabledIsReportedWithoutQueryingTheFile(): void
    {
        mkdir($this->projectRoot . '/var');
        $dbPath = $this->projectRoot . '/var/data.db';
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');

        putenv('DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db');

        $this->kernel = new DoctorCommandTestKernel($this->projectRoot, false);
        $this->kernel->boot();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $tester = new CommandTester($application->find('tfsapp:doctor'));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertStringContainsString('sqlite_pragmas', $display);
        self::assertStringContainsString('disabled', $display);
        self::assertStringNotContainsString('sqlite_journal_mode', $display);
    }

    public function testUploadDirFromEnvironmentIsReported(): void
    {
        mkdir($this->baseDir . '/uploads');
        putenv('APP_UPLOAD_DIR=' . $this->baseDir . '/uploads');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertMatchesRegularExpression('/upload_dir\s+' . preg_quote($this->baseDir . '/uploads', '/') . '\s/', $display);
        self::assertMatchesRegularExpression('/upload_dir_source\s+APP_UPLOAD_DIR\s/', $display);
        self::assertMatchesRegularExpression('/upload_dir_writable\s+yes\s/', $display);
        self::assertStringNotContainsString('not writable', $display);
    }

    public function testUploadDirFallbackIsReportedAsSuch(): void
    {
        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertMatchesRegularExpression('/upload_dir\s+' . preg_quote($this->projectRoot . '/var/uploads', '/') . '\s/', $display);
        self::assertMatchesRegularExpression('/upload_dir_source\s+fallback \(not set\)/', $display);
        self::assertMatchesRegularExpression('/upload_dir_writable\s+yes \(created on first write\)/', $display);
        self::assertStringNotContainsString('not writable', $display);
    }

    public function testUnwritableUploadDirIsFlaggedWithAWarning(): void
    {
        if (\function_exists('posix_geteuid') && 0 === posix_geteuid()) {
            self::markTestSkipped('root writes through any permission bits.');
        }

        mkdir($this->baseDir . '/uploads');
        chmod($this->baseDir . '/uploads', 0555);
        putenv('APP_UPLOAD_DIR=' . $this->baseDir . '/uploads');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertMatchesRegularExpression('/upload_dir_writable\s+no\s/', $display);
        self::assertMatchesRegularExpression('/is\s+not\s+writable/', $display);
    }

    public function testNoTemplatesDirDoesNotWarn(): void
    {
        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringNotContainsString('Off-origin assets', $tester->getDisplay());
    }

    public function testCleanTemplatesDirDoesNotWarn(): void
    {
        mkdir($this->projectRoot . '/templates');
        file_put_contents($this->projectRoot . '/templates/base.html.twig', '<html></html>');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringNotContainsString('Off-origin assets', $tester->getDisplay());
    }

    public function testFrankenphpHotReloadBlockIsFlaggedWithAWarning(): void
    {
        mkdir($this->projectRoot . '/templates');
        file_put_contents(
            $this->projectRoot . '/templates/base.html.twig',
            '<html>{% if hot_reload %}<script src="https://cdn.jsdelivr.net/npm/idiomorph"></script>{% endif %}</html>',
        );

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertStringContainsString('Off-origin assets found under templates/', $display);
        self::assertStringContainsString('base.html.twig', $display);
    }

    public function testOffOriginAssetInNestedTemplateIsFlagged(): void
    {
        mkdir($this->projectRoot . '/templates/partials', 0777, true);
        file_put_contents(
            $this->projectRoot . '/templates/partials/_footer.html.twig',
            '<meta name="frankenphp-hot-reload:url" content="ws://127.0.0.1">',
        );

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertStringContainsString('Off-origin assets found under templates/', $display);
        self::assertStringContainsString('partials' . \DIRECTORY_SEPARATOR . '_footer.html.twig', $display);
    }

    /**
     * @param list<string>|null $receiverTransports
     */
    private function createTester(?array $receiverTransports = null, ?bool $updateCheckerAvailable = null): CommandTester
    {
        $this->kernel = new DoctorCommandTestKernel(
            $this->projectRoot,
            receiverTransports: $receiverTransports,
            updateCheckerAvailable: $updateCheckerAvailable,
        );
        $this->kernel->boot();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        return new CommandTester($application->find('tfsapp:doctor'));
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
