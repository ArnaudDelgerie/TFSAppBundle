<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Command;

use ArnaudDelgerie\TFSAppBundle\Bridge\SecretStoreInterface;
use ArnaudDelgerie\TFSAppBundle\Bridge\UpdateCheckerInterface;
use ArnaudDelgerie\TFSAppBundle\StationContext\StationContextInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'tfsapp:doctor', description: 'Print the resolved station context and bridge availability')]
final class DoctorCommand extends Command
{
    public function __construct(
        private readonly StationContextInterface $context,
        private readonly SecretStoreInterface $secretStore,
        private readonly UpdateCheckerInterface $updateChecker,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->table(['Field', 'Value'], [
            ['identifier', $this->context->identifier()],
            ['version', $this->context->version()],
            ['running_under_station', self::formatBool($this->context->isRunningUnderStation())],
            ['async_worker', self::formatBool($this->context->isAsyncWorker())],
            ['keyring_available', self::formatBool($this->context->isKeyringAvailable())],
            ['bridge_enabled', self::formatBool($this->context->isBridgeEnabled())],
            ['secrets_available', self::formatBool($this->secretStore->isAvailable())],
            ['update_check_available', self::formatBool($this->updateChecker->isAvailable())],
        ]);

        return Command::SUCCESS;
    }

    private static function formatBool(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
