<?php

namespace App\Command;

use App\Service\AlertService;
use App\Service\AlertMailer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
#[AsCommand(
    name: 'app:inventory:check',
    description: 'Check low stock and expiration alerts'
)]
class SendAlertsCommand extends Command
{
    

    public function __construct(
        private AlertService $alertService,
        private AlertMailer $alertMailer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
{
    $grouped = $this->alertService->getAlertsGrouped();

    $this->alertMailer->sendAlerts($grouped);

    $output->writeln("✔ Alertas enviadas");

    return Command::SUCCESS;
}
}