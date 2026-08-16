<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Command;

use ArnaudDelgerie\TFSAppBundle\Bridge\SecretStoreInterface;
use ArnaudDelgerie\TFSAppBundle\Bridge\UpdateCheckerInterface;
use ArnaudDelgerie\TFSAppBundle\Doctrine\SqlitePragmas;
use ArnaudDelgerie\TFSAppBundle\StationContext\StationContextInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ServiceCollectionInterface;

#[AsCommand(name: 'tfsapp:doctor', description: 'Print the resolved station context and bridge availability')]
final class DoctorCommand extends Command
{
    public function __construct(
        private readonly StationContextInterface $context,
        private readonly SecretStoreInterface $secretStore,
        private readonly UpdateCheckerInterface $updateChecker,
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
            ['running_under_station', self::formatBool($this->context->isRunningUnderStation())],
            ['async_worker', self::formatBool($this->context->isAsyncWorker())],
            ['worker_transports', implode(', ', $this->context->workerTransports()) ?: '(none)'],
            ['keyring_available', self::formatBool($this->context->isKeyringAvailable())],
            ['bridge_enabled', self::formatBool($this->context->isBridgeEnabled())],
            ['secrets_available', self::formatBool($this->secretStore->isAvailable())],
            ['update_check_available', self::formatBool($this->updateChecker->isAvailable())],
        ];

        [$databaseRows, $databaseWarning] = $this->inspectDatabase();

        $io->table(['Field', 'Value'], [...$rows, ...$databaseRows]);

        if (null !== $databaseWarning) {
            $io->warning($databaseWarning);
        }

        $this->warnMissingWorkerTransports($io);
        $this->warnOffOriginAssets($io);

        return Command::SUCCESS;
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
            return [$rows, null];
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
                . ' — nothing has migrated it yet for this run (CONTRACT.md §3: '
                . '"make tauri-dev" ignores "commands", so the station-injected DATABASE_URL never gets its schema in dev mode).',
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
            . ' — CONTRACT.md §4 forbids anything but \'self\' under the station\'s CSP.'
            . ' This is typically the FrankenPHP hot-reload block Flex scaffolds at the end of base.html.twig; remove it before packaging.',
            implode(', ', $offenders),
        ));
    }

    private static function formatBool(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
