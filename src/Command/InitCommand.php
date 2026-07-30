<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'tfsapp:init', description: 'Generate tfsapp.config.json at the project root')]
final class InitCommand extends Command
{
    public function __construct(private readonly KernelInterface $kernel)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $projectDir = rtrim($this->kernel->getProjectDir(), '/');

        $this->ensureConfig($projectDir, $io);
        $this->ensureBuildDir($projectDir, $io);
        $this->ensureGitignore($projectDir, $io);
        $this->ensureChangelog($projectDir, $io);
        $this->ensureReadme($projectDir, $io);

        return Command::SUCCESS;
    }

    private function ensureConfig(string $projectDir, SymfonyStyle $io): void
    {
        $configPath = $projectDir . '/tfsapp.config.json';

        if (is_file($configPath)) {
            $io->success(sprintf('%s already exists.', $configPath));

            return;
        }

        $defaultProjectName = self::slugify(basename($projectDir));

        $projectName = $io->ask('project_name (lowercase slug, e.g. "my-app")', $defaultProjectName, self::projectNameValidator(...));
        $productName = $io->ask('product_name (free-form display name, e.g. "My App")', self::humanize($projectName), self::productNameValidator(...));
        $identifier = $io->ask('identifier (lowercase reverse-domain, e.g. "dev.local.my-app")', 'dev.local.' . $projectName, self::identifierValidator(...));
        $appVersion = $io->ask('app_version (semver MAJOR.MINOR.PATCH, e.g. "1.2.3")', '0.1.0', self::appVersionValidator(...));

        $asyncWorker = $io->confirm('async_worker', false);
        $releasesRepo = $io->ask('releases_repo (bare name or owner/repo, e.g. "myorg/myapp"; leave empty to skip)', '', self::releasesRepoValidator(...));

        $config = [
            'project_name' => $projectName,
            'product_name' => $productName,
            'identifier' => $identifier,
            'app_version' => $appVersion,
        ];

        if ($asyncWorker) {
            $config['async_worker'] = true;
        }

        if ('' !== $releasesRepo) {
            $config['releases_repo'] = $releasesRepo;
        }

        $config['commands'] = self::buildCommands($projectDir);
        $config['actions'] = self::actionsSkeleton();

        file_put_contents(
            $configPath,
            json_encode($config, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . \PHP_EOL,
        );

        $io->success(sprintf('Created %s', $configPath));
    }

    private function ensureBuildDir(string $projectDir, SymfonyStyle $io): void
    {
        $buildDir = $projectDir . '/tfsapp_build';

        if (is_dir($buildDir)) {
            $io->success(sprintf('%s already exists.', $buildDir));

            return;
        }

        mkdir($buildDir);

        $io->success(sprintf('Created %s', $buildDir));
    }

    private function ensureGitignore(string $projectDir, SymfonyStyle $io): void
    {
        $gitignorePath = $projectDir . '/.gitignore';
        $entry = '/tfsapp_build/';

        if (!is_file($gitignorePath)) {
            file_put_contents($gitignorePath, $entry . \PHP_EOL);
            $io->success(sprintf('Created %s with %s', $gitignorePath, $entry));

            return;
        }

        $contents = file_get_contents($gitignorePath);
        $lines = array_map('trim', preg_split('/\R/', $contents ?: ''));

        if (\in_array($entry, $lines, true)) {
            $io->success(sprintf('%s already ignores %s', $gitignorePath, $entry));

            return;
        }

        $needsLeadingNewline = '' !== $contents && !str_ends_with($contents, "\n");
        file_put_contents(
            $gitignorePath,
            ($needsLeadingNewline ? \PHP_EOL : '') . $entry . \PHP_EOL,
            \FILE_APPEND,
        );

        $io->success(sprintf('Added %s to %s', $entry, $gitignorePath));
    }

    private function ensureChangelog(string $projectDir, SymfonyStyle $io): void
    {
        $changelogPath = $projectDir . '/CHANGELOG.md';

        if (is_file($changelogPath)) {
            $io->success(sprintf('%s already exists.', $changelogPath));

            return;
        }

        $appVersion = self::resolveAppVersion($projectDir . '/tfsapp.config.json');

        if (null === $appVersion) {
            $io->warning(sprintf('Could not resolve app_version from %s/tfsapp.config.json; skipping CHANGELOG.md.', $projectDir));

            return;
        }

        $contents = '# Changelog' . \PHP_EOL
            . \PHP_EOL
            . '## ' . $appVersion . \PHP_EOL
            . \PHP_EOL
            . '- Initial release.' . \PHP_EOL;

        file_put_contents($changelogPath, $contents);

        $io->success(sprintf('Created %s', $changelogPath));
    }

    private function ensureReadme(string $projectDir, SymfonyStyle $io): void
    {
        $readmePath = $projectDir . '/TFSAPP_README.md';

        if (is_file($readmePath)) {
            $io->success(sprintf('%s already exists.', $readmePath));

            return;
        }

        $identity = self::resolveIdentity($projectDir . '/tfsapp.config.json');

        if (null === $identity) {
            $io->warning(sprintf('Could not resolve product_name/identifier from %s/tfsapp.config.json; skipping TFSAPP_README.md.', $projectDir));

            return;
        }

        [$productName, $identifier] = $identity;

        $contents = '# ' . $productName . \PHP_EOL
            . \PHP_EOL
            . '`' . $identifier . '` — managed by TFSAppWorkstation; see its `CONTRACT.md` for the station ↔ project contract.' . \PHP_EOL
            . \PHP_EOL
            . '## Subcommands' . \PHP_EOL
            . \PHP_EOL
            . 'Packaged only: run these against the built `./<app>.AppImage`, not `bin/console` — in dev mode each one just prints that and exits `2`.' . \PHP_EOL
            . \PHP_EOL
            . self::renderSubcommands()
            . \PHP_EOL
            . '## Optional config fields' . \PHP_EOL
            . \PHP_EOL
            . '`tfsapp:init` does not scaffold `actions` beyond its all-`false` skeleton, nor `run`, `app_port`, `icon_path`, or `splash_*` — add these to `tfsapp.config.json` by hand when needed.' . \PHP_EOL;

        file_put_contents($readmePath, $contents);

        $io->success(sprintf('Created %s', $readmePath));
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function resolveIdentity(string $configPath): ?array
    {
        if (!is_file($configPath)) {
            return null;
        }

        $contents = file_get_contents($configPath);

        if (false === $contents) {
            return null;
        }

        try {
            $config = json_decode($contents, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($config)
            || !isset($config['product_name'], $config['identifier'])
            || !\is_string($config['product_name'])
            || !\is_string($config['identifier'])
        ) {
            return null;
        }

        return [$config['product_name'], $config['identifier']];
    }

    private static function renderSubcommands(): string
    {
        $lines = [
            '- `--version` — print the running version and the rollback target.',
            '- `--help` — print usage and the full subcommand list.',
            '- `--update` — update the installed app to the latest release.',
            '- `--rollback` — undo the last update.',
            '- `--uninstall [--purge]` — remove the app\'s data (and, with `--purge`, its keyring entries too).',
            '- `--export <path>` — write a backup (database, version, manifest) to `<path>`.',
            '- `--import <path>` — seed a fresh install\'s data from a backup made with `--export`.',
            '- `run <alias> [args...]` — run one of this app\'s named `bin/console` aliases in the foreground.',
            '- `--yes` / `-y` — skip the confirmation prompt on `--update`, `--rollback`, `--uninstall`.',
            '',
            'This list can drift from the packaged build over time; `./<app>.AppImage --help` is the authoritative one.',
        ];

        return implode(\PHP_EOL, $lines) . \PHP_EOL;
    }

    private static function resolveAppVersion(string $configPath): ?string
    {
        if (!is_file($configPath)) {
            return null;
        }

        $contents = file_get_contents($configPath);

        if (false === $contents) {
            return null;
        }

        try {
            $config = json_decode($contents, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($config) || !isset($config['app_version']) || !\is_string($config['app_version'])) {
            return null;
        }

        return $config['app_version'];
    }

    private static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private static function humanize(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $value));
    }

    private static function projectNameValidator(?string $answer): string
    {
        if (null === $answer || '' === $answer || preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $answer) !== 1) {
            throw new \InvalidArgumentException('project_name must be a machine-friendly slug (lowercase letters, digits, "-" or "_", no whitespace).');
        }

        return $answer;
    }

    private static function productNameValidator(?string $answer): string
    {
        if (null === $answer || '' === trim($answer)) {
            throw new \InvalidArgumentException('product_name must not be empty.');
        }

        return $answer;
    }

    private static function identifierValidator(?string $answer): string
    {
        if (null === $answer || preg_match('/^[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)+$/', $answer) !== 1) {
            throw new \InvalidArgumentException('identifier must be a reverse-domain string (e.g. dev.local.myapp).');
        }

        return $answer;
    }

    private static function appVersionValidator(?string $answer): string
    {
        if (null === $answer || preg_match('/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)$/', $answer) !== 1) {
            throw new \InvalidArgumentException('app_version must be canonical MAJOR.MINOR.PATCH semver with no leading zeros (e.g. 1.2.3).');
        }

        return $answer;
    }

    private static function releasesRepoValidator(?string $answer): string
    {
        if (null === $answer || '' === $answer) {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+)?$/', $answer) !== 1) {
            throw new \InvalidArgumentException('releases_repo must be a bare repo name or an owner/repo pair (e.g. myapp or myorg/myapp).');
        }

        return $answer;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function buildCommands(string $projectDir): array
    {
        $commands = [
            'pre-build' => [
                'composer install --no-dev --no-scripts --optimize-autoloader',
                'php bin/console cache:clear --env=prod --no-debug',
                'php bin/console cache:warmup --env=prod --no-debug',
            ],
            'post-build' => [
                'composer install',
            ],
        ];

        if (self::isPackageInstalled($projectDir, 'doctrine/doctrine-migrations-bundle')) {
            $commands['pre-install'] = ['doctrine:migrations:migrate --no-interaction'];
            $commands['pre-update'] = ['doctrine:migrations:migrate --no-interaction'];
        }

        return $commands;
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

    /**
     * @return array<string, array<string, bool|list<string>>>
     */
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
        ];
    }
}
