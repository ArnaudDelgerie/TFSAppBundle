<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Command;

use ArnaudDelgerie\TFSAppBundle\Bridge\SecretStoreInterface;
use ArnaudDelgerie\TFSAppBundle\Bridge\UpdateCheckerInterface;
use ArnaudDelgerie\TFSAppBundle\Doctrine\SqlitePragmas;
use ArnaudDelgerie\TFSAppBundle\HubContext\HubContextInterface;
use ArnaudDelgerie\TFSAppBundle\Storage\UploadStorageInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ServiceCollectionInterface;

#[AsCommand(name: 'tfsapp:doctor', description: 'Print the resolved hub context and bridge availability')]
final class DoctorCommand extends Command
{
    /**
     * A save_path configured from APP_SESSION_DIR resolves to whatever the
     * env holds when the container first reads it, so the check plants a
     * sentinel there beforehand: a configuration that reads the env comes
     * back as the sentinel, anything else (a plain path, or nothing at all)
     * does not.
     */
    private const SESSION_DIR_SENTINEL = 'tfsapp-doctor-session-dir-sentinel';

    public function __construct(
        private readonly HubContextInterface $context,
        private readonly SecretStoreInterface $secretStore,
        private readonly UpdateCheckerInterface $updateChecker,
        private readonly UploadStorageInterface $uploadStorage,
        private readonly KernelInterface $kernel,
        private readonly ?ServiceCollectionInterface $receiverLocator = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [
            ['identifier', $this->context->identifier()],
            ['version', $this->context->version()],
            ['running_under_hub', self::formatBool($this->context->isRunningUnderHub())],
            ['async_worker', self::formatBool($this->context->isAsyncWorker())],
            ['worker_transports', implode(', ', $this->context->workerTransports()) ?: '(none)'],
            ['keyring_available', self::formatBool($this->context->isKeyringAvailable())],
            ['bridge_enabled', self::formatBool($this->context->isBridgeEnabled())],
            ['secrets_available', self::formatBool($this->secretStore->isAvailable())],
            ['update_check_available', self::formatBool($this->updateChecker->isAvailable())],
        ];

        [$databaseRows, $databaseWarning] = $this->inspectDatabase();
        [$uploadRows, $uploadWarning] = $this->inspectUploadDir();

        $io->table(['Field', 'Value'], [...$rows, ...$databaseRows, ...$uploadRows]);

        if (null !== $databaseWarning) {
            $io->warning($databaseWarning);
        }

        if (null !== $uploadWarning) {
            $io->warning($uploadWarning);
        }

        $this->warnMissingWorkerTransports($io);
        $this->warnOffOriginAssets($io);
        $this->warnOffHubSessionDir($io);
        $this->warnUndeclaredOrUncompiledAssets($io);

        return Command::SUCCESS;
    }

    /**
     * The compiled configuration only defines the session.save_path
     * parameter when sessions are enabled; its resolved value is what the
     * session handler will use.
     */
    private function warnOffHubSessionDir(SymfonyStyle $io): void
    {
        $container = $this->kernel->getContainer();

        if (!$container->hasParameter('session.save_path')) {
            return;
        }

        $sessionDir = self::readEnv('APP_SESSION_DIR');
        $injected = null !== $sessionDir;

        if (!$injected) {
            $sessionDir = self::SESSION_DIR_SENTINEL;
            $_ENV['APP_SESSION_DIR'] = $sessionDir;
            $_SERVER['APP_SESSION_DIR'] = $sessionDir;
        }

        try {
            $savePath = $container->getParameter('session.save_path');
        } finally {
            if (!$injected) {
                unset($_ENV['APP_SESSION_DIR'], $_SERVER['APP_SESSION_DIR']);
            }
        }

        if ($savePath === $sessionDir) {
            return;
        }

        $io->warning(
            'Sessions are enabled but their save path does not come from APP_SESSION_DIR — with the native'
            . ' handler the bundled PHP\'s session.save_path is empty, so sessions land in /tmp: shared by'
            . ' every app on the machine and lost at reboot. Set save_path: \'%env(default::APP_SESSION_DIR)%\''
            . ' under framework.session in config/packages/framework.yaml; the default:: keeps the app booting'
            . ' outside the hub.',
        );
    }

    private function warnMissingWorkerTransports(SymfonyStyle $io): void
    {
        $consumedTransports = $this->context->workerTransports();

        if (null === $this->receiverLocator || [] === $consumedTransports) {
            return;
        }

        $missingTransports = array_values(array_diff(
            $consumedTransports,
            array_keys($this->receiverLocator->getProvidedServices()),
        ));

        if ([] === $missingTransports) {
            return;
        }

        $io->warning(sprintf(
            'The hub is running messenger:consume on transport(s) this app does not configure: %s. '
            . 'Those consumers exit immediately, so the hub gives up on them after a few retries.',
            implode(', ', $missingTransports),
        ));
    }

    /**
     * The storage creates its root on first write, so a root that does not
     * exist yet is writable when its nearest existing ancestor is.
     *
     * @return array{0: list<array{0: string, 1: string}>, 1: string|null}
     */
    private function inspectUploadDir(): array
    {
        $root = $this->uploadStorage->root();

        $rows = [
            ['upload_dir', $root],
            ['upload_dir_source', null !== self::readEnv('APP_UPLOAD_DIR') ? 'APP_UPLOAD_DIR' : 'fallback (not set)'],
        ];

        $ancestor = $root;

        while (!file_exists($ancestor) && \dirname($ancestor) !== $ancestor) {
            $ancestor = \dirname($ancestor);
        }

        $writable = is_dir($ancestor) && is_writable($ancestor);
        $rows[] = ['upload_dir_writable', match (true) {
            !$writable => 'no',
            $ancestor !== $root => 'yes (created on first write)',
            default => 'yes',
        }];

        if ($writable) {
            return [$rows, null];
        }

        return [$rows, sprintf(
            'The upload directory %s is not writable by this process. '
            . 'Every UploadStorage write, store and delete will fail until it is.',
            $root,
        )];
    }

    /**
     * @return array{0: list<array{0: string, 1: string}>, 1: string|null}
     */
    private function inspectDatabase(): array
    {
        $rawUrl = self::readEnv('DATABASE_URL');

        if (null === $rawUrl) {
            return [[['database_url', '(not set)']], null];
        }

        $resolvedUrl = $this->resolveContainerParameters($rawUrl);
        $rows = [['database_url', $resolvedUrl]];

        if (!str_starts_with($resolvedUrl, 'sqlite:')) {
            return [$rows, sprintf(
                'DATABASE_URL is not SQLite — it resolves to %s. Migrations are generated against that server,'
                . ' and the database the installed app runs on is SQLite (CONTRACT.md §3), so they fail at install'
                . ' time. Set DATABASE_URL="sqlite:///%%kernel.project_dir%%/var/data/app.db" in .env — the same line'
                . ' tfsapp-hub dev injects.',
                explode(':', $resolvedUrl, 2)[0],
            )];
        }

        if (':memory:' === substr($resolvedUrl, \strlen('sqlite:'))) {
            return [$rows, null];
        }

        $path = self::sqliteFilePath($resolvedUrl);

        if (null === $path) {
            return [$rows, sprintf('Could not determine the SQLite file path from %s.', $resolvedUrl)];
        }

        $fileExists = is_file($path);
        $rows[] = ['database_file_exists', self::formatBool($fileExists)];

        if (!$fileExists) {
            return [$rows, sprintf(
                'SQLite database file does not exist: %s'
                . ' — nothing has migrated it yet for this run (CONTRACT.md §9: '
                . '"dev" never runs "pre-install", so run your own console\'s migrations against the injected DATABASE_URL).',
                $path,
            )];
        }

        $hasTables = self::sqliteHasTables($path);

        if (null === $hasTables) {
            $rows[] = ['database_tables', 'unknown (pdo_sqlite unavailable)'];

            return [$rows, null];
        }

        $rows[] = ['database_tables', $hasTables ? 'present' : 'none'];

        [$pragmaRows, $pragmaWarning] = $this->inspectSqlitePragmas($path);
        $rows = [...$rows, ...$pragmaRows];

        if (!$hasTables) {
            return [$rows, sprintf(
                'SQLite database file %s exists but holds no tables — it may have been migrated against a'
                . ' different DATABASE_URL (CONTRACT.md §3: dev-mode `bin/console` reads the project\'s own .env,'
                . ' which can point at a different file than the one this process resolved above).',
                $path,
            )];
        }

        return [$rows, $pragmaWarning];
    }

    /**
     * @return array{0: list<array{0: string, 1: string}>, 1: string|null}
     */
    private function inspectSqlitePragmas(string $path): array
    {
        $container = $this->kernel->getContainer();
        $enabled = $container->hasParameter('tfsapp.sqlite_pragmas') && $container->getParameter('tfsapp.sqlite_pragmas');

        if (!$enabled) {
            return [[['sqlite_pragmas', 'disabled (sqlite_pragmas: false)']], null];
        }

        if (!\in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            return [[['sqlite_journal_mode', 'unknown (pdo_sqlite unavailable)']], null];
        }

        try {
            $pdo = new \PDO('sqlite:' . $path);
            $journalMode = (string) $pdo->query('PRAGMA journal_mode')->fetchColumn();
        } catch (\Throwable) {
            return [[['sqlite_journal_mode', 'unknown (could not open the file)']], null];
        }

        $rows = [
            ['sqlite_journal_mode', $journalMode],
            ['sqlite_busy_timeout_ms', (string) SqlitePragmas::BUSY_TIMEOUT_MS],
        ];

        if ('wal' !== $journalMode) {
            return [$rows, sprintf(
                'SQLite database file %s is in "%s" journal mode, not WAL — SqlitePragmaDriver sets WAL on'
                . ' connect, so this file has not yet been opened through Doctrine in this project. Until it is,'
                . ' any writer (the async worker) blocks every reader (the web process) on this file.',
                $path,
                $journalMode,
            )];
        }

        return [$rows, null];
    }

    private function resolveContainerParameters(string $value): string
    {
        $container = $this->kernel->getContainer();

        return preg_replace_callback('/%([a-zA-Z0-9_.]+)%/', static function (array $matches) use ($container): string {
            try {
                $parameter = $container->getParameter($matches[1]);
            } catch (\Throwable) {
                return $matches[0];
            }

            return \is_scalar($parameter) ? (string) $parameter : $matches[0];
        }, $value) ?? $value;
    }

    /**
     * parse_url() returns false for "scheme:///path" on any scheme it doesn't special-case (sqlite isn't
     * one), so the "sqlite:///<absolute path>" convention Doctrine/CONTRACT.md §3 use needs parsing by hand.
     */
    private static function sqliteFilePath(string $url): ?string
    {
        $rest = substr($url, \strlen('sqlite:'));

        if (str_starts_with($rest, '///')) {
            return substr($rest, 2);
        }

        if (str_starts_with($rest, '/') && !str_starts_with($rest, '//')) {
            return $rest;
        }

        return null;
    }

    private static function readEnv(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) && '' !== $value ? $value : null;
    }

    private static function sqliteHasTables(string $path): ?bool
    {
        if (!\in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            return null;
        }

        try {
            $pdo = new \PDO('sqlite:' . $path);
            $count = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn();
        } catch (\Throwable) {
            return null;
        }

        return $count > 0;
    }

    private function warnOffOriginAssets(SymfonyStyle $io): void
    {
        $templatesDir = rtrim($this->kernel->getProjectDir(), '/') . '/templates';

        if (!is_dir($templatesDir)) {
            return;
        }

        $needles = ['cdn.jsdelivr.net', 'frankenphp-hot-reload'];
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($templatesDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (false === $contents) {
                continue;
            }

            $contents = self::stripHotReloadGate($contents);

            foreach ($needles as $needle) {
                if (str_contains($contents, $needle)) {
                    $offenders[] = substr($file->getPathname(), \strlen($templatesDir) + 1);

                    continue 2;
                }
            }
        }

        if ([] === $offenders) {
            return;
        }

        $io->warning(sprintf(
            'Off-origin assets found under templates/: %s'
            . ' — CONTRACT.md §4 forbids anything but \'self\' under the hub\'s CSP.',
            implode(', ', $offenders),
        ));
    }

    /**
     * The hub never sets FRANKENPHP_HOT_RELOAD, so the hot-reload block Flex
     * scaffolds renders nothing and loads nothing: what sits inside it is not
     * an asset the app serves, and must not be scanned for off-origin URLs.
     * Flex gates it either on the server value directly or on the variable
     * its base template sets from that value, so match both spellings; a
     * negated gate (`{% if not ... %}`) is a live block and stays scanned.
     */
    private static function stripHotReloadGate(string $contents): string
    {
        return preg_replace('/\{%\s*if\s+frankenphp_hot_reload\s*%\}.*?\{%\s*endif\s*%\}/si', '', $contents) ?? $contents;
    }

    /**
     * Only AssetMapper, the `webapp` recipe's default, is checked — the
     * doctor stays silent for apps without a build step of their own.
     */
    private function warnUndeclaredOrUncompiledAssets(SymfonyStyle $io): void
    {
        $projectDir = rtrim($this->kernel->getProjectDir(), '/');

        if (!self::isPackageInstalled($projectDir, 'symfony/asset-mapper')) {
            return;
        }

        if (!is_file($projectDir . '/public/assets/manifest.json')) {
            $io->warning(
                'The AssetMapper output under public/assets/ is not compiled — in prod, /assets/… answers 404'
                . ' until asset-map:compile has run, and tfsapp-hub dev runs with APP_DEBUG=1 and serves them on'
                . ' the fly, so everything works in dev and breaks once installed. Run'
                . ' APP_ENV=prod bin/console asset-map:compile.',
            );
        }

        if (!self::buildOutputsDeclarePublicAssets($projectDir)) {
            $io->warning(
                'public/assets/ is not declared in tfsapp.config.json\'s build_outputs — the directory is'
                . ' gitignored, so the compiled assets only ship once declared. Add "public/assets" to'
                . ' build_outputs.',
            );
        }
    }

    private static function buildOutputsDeclarePublicAssets(string $projectDir): bool
    {
        $configPath = $projectDir . '/tfsapp.config.json';

        if (!is_file($configPath)) {
            return false;
        }

        $contents = file_get_contents($configPath);

        if (false === $contents) {
            return false;
        }

        try {
            $config = json_decode($contents, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        $buildOutputs = \is_array($config) ? ($config['build_outputs'] ?? null) : null;

        return \is_array($buildOutputs) && \in_array('public/assets', $buildOutputs, true);
    }

    private static function isPackageInstalled(string $projectDir, string $packageName): bool
    {
        $lockPath = $projectDir . '/composer.lock';

        if (!is_file($lockPath)) {
            return false;
        }

        $contents = file_get_contents($lockPath);

        if (false === $contents) {
            return false;
        }

        try {
            $lock = json_decode($contents, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        if (!\is_array($lock)) {
            return false;
        }

        foreach (['packages', 'packages-dev'] as $section) {
            foreach ($lock[$section] ?? [] as $package) {
                if (\is_array($package) && ($package['name'] ?? null) === $packageName) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function formatBool(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
