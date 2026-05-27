<?php

namespace App\Command;

use App\Service\PurchaseCsvImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:import:purchases',
    description: 'Importa compras desde CSV'
)]
class ImportPurchasesCommand extends Command
{
    public function __construct(
        private PurchaseCsvImporter $importer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta del CSV')
            ->addArgument(
                'mode',
                InputArgument::OPTIONAL,
                'Modo de importación (strict | create)',
                PurchaseCsvImporter::MODE_CREATE
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $mode = strtolower($input->getArgument('mode'));

        if (!in_array($mode, [
            PurchaseCsvImporter::MODE_STRICT,
            PurchaseCsvImporter::MODE_CREATE
        ])) {
            $output->writeln("<error>Modo inválido: $mode (usa strict o create)</error>");
            return Command::FAILURE;
        }

        $output->writeln("📥 Importando compras...");
        $output->writeln("📄 Archivo: $file");
        $output->writeln("⚙️ Modo: $mode");

        try {
            $this->importer->import($file, $mode);

            $output->writeln("<info>✅ Import completado correctamente</info>");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>❌ Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}