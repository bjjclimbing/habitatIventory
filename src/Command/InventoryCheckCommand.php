<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\InventoryBatch;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:inventory:check',
    description: 'Check low stock and expiration alerts'
)]
class InventoryCheckCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTimeImmutable('today');
        $limitDate = $today->modify('+30 days');

        // ======================
        // 1. STOCK BAJO
        // ======================
        $qb = $this->em->createQueryBuilder();

        $results = $qb
            ->select('p.id, p.name, p.minstock, SUM(b.quantity) as totalStock')
            ->from(Product::class, 'p')
            ->leftJoin('p.batches', 'b')
            ->groupBy('p.id')
            ->getQuery()
            ->getResult();

        foreach ($results as $row) {
            $totalStock = (int) ($row['totalStock'] ?? 0);
            $minStock = (int) ($row['minstock'] ?? 0);

            if ($totalStock < $minStock) {
                $output->writeln(
                    "<comment>LOW STOCK: {$row['name']} ({$totalStock}/{$minStock})</comment>"
                );

                // 👉 aquí luego mandas email / telegram
            }
        }

        // ======================
        // 2. CADUCIDAD PRÓXIMA
        // ======================
        $qb = $this->em->createQueryBuilder();

        $expiring = $qb
            ->select('b, p')
            ->from(InventoryBatch::class, 'b')
            ->join('b.product', 'p')
            ->where('b.expirationDate IS NOT NULL')
            ->andWhere('b.expirationDate <= :limit')
            ->andWhere('b.expirationDate >= :today')
            ->setParameter('limit', $limitDate)
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        foreach ($expiring as $batch) {
            $product = $batch->getProduct();

            $output->writeln(
                "<comment>EXPIRING: {$product->getName()} ({$batch->getExpirationDate()->format('Y-m-d')})</comment>"
            );
        }

        // ======================
        // 3. YA CADUCADOS
        // ======================
        $expired = $this->em->createQueryBuilder()
            ->select('b, p')
            ->from(InventoryBatch::class, 'b')
            ->join('b.product', 'p')
            ->where('b.expirationDate < :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        foreach ($expired as $batch) {
            $product = $batch->getProduct();

            $output->writeln(
                "<error>EXPIRED: {$product->getName()}</error>"
            );
        }

        return Command::SUCCESS;
    }
}