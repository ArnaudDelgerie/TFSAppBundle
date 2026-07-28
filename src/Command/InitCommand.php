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
        $configPath = $projectDir . '/tfsapp.config.json';

        if (is_file($configPath)) {
            $io->success(sprintf('%s already exists.', $configPath));

            return Command::SUCCESS;
        }

        $defaultProjectName = self::slugify(basename($projectDir));

        $projectName = $io->ask('project_name', $defaultProjectName, self::projectNameValidator(...));
        $productName = $io->ask('product_name', self::humanize($projectName), self::productNameValidator(...));
        $identifier = $io->ask('identifier', 'dev.local.' . $projectName, self::identifierValidator(...));
        $appVersion = $io->ask('app_version', '0.1.0', self::appVersionValidator(...));

        $asyncWorker = $io->confirm('async_worker', false);
        $releasesRepo = $io->ask('releases_repo', '', self::releasesRepoValidator(...));

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

        return Command::SUCCESS;
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
