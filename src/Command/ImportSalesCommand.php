<?php

namespace App\Command;

use App\Service\SalesCsvImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:import:sales',
    description: 'Importa ventas desde CSV'
)]
class ImportSalesCommand extends Command
{
    public function __construct(
        private SalesCsvImporter $importer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Ruta del CSV');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');

        $output->writeln("📤 Importando ventas...");
        $output->writeln("📄 Archivo: $file");

        try {
            $this->importer->import($file);

            $output->writeln("<info>✅ Ventas importadas correctamente</info>");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>❌ Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}