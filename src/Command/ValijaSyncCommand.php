<?php

namespace App\Command;

use App\Repository\ValijaRepository;
use App\Service\ValijaSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:valija:sync',
    description: 'Sincroniza el stock de las valijas'
)]
class ValijaSyncCommand extends Command
{
    public function __construct(
        private ValijaRepository $valijaRepo,
        private ValijaSyncService $syncService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $valijas = $this->valijaRepo->findAll();

        foreach ($valijas as $valija) {
            $output->writeln("🔄 Sync valija: " . $valija->getName());
            $this->syncService->sync($valija);
        }

        return Command::SUCCESS;
    }
}