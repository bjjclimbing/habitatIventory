<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\StockMovement;
use App\Entity\InventoryBatch;
use Doctrine\ORM\EntityManagerInterface;

class StockService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    // ======================
    // CONSUMIR STOCK (FIFO)
    // ======================
    public function consume(Product $product, int $quantity): void
{
    $this->em->beginTransaction();

    try {
        $remaining = $quantity;

        $batches = $this->em->getRepository(InventoryBatch::class)
            ->createQueryBuilder('b')
            ->where('b.product = :product')
            ->andWhere('b.quantity > 0')
            ->orderBy('b.expirationDate', 'ASC')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult();

        foreach ($batches as $batch) {

            if ($remaining <= 0) break;

            // 🔒 lock
            $this->em->lock($batch, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);

            $available = $batch->getQuantity();

            if ($available <= 0) continue;

            $take = min($available, $remaining);

            $batch->decrease($take);

            // 🧾 movimiento por batch (IMPORTANTE)
            $movement = new StockMovement();
            $movement->setProduct($product);
            $movement->setBatch($batch);
            $movement->setType(StockMovement::TYPE_OUT);
            $movement->setQuantity($take);

            $this->em->persist($movement);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new \RuntimeException('Stock insuficiente');
        }

        $this->em->flush();
        $this->em->commit();

    } catch (\Exception $e) {
        $this->em->rollback();
        throw $e;
    }
}

    // ======================
    // AÑADIR STOCK
    // ======================
    public function addStock(Product $product, int $quantity, ?\DateTime $expiration = null,
    ?float $commissionPercent = null): void
{
    $batch = new InventoryBatch();
    $batch->setProduct($product);
    $batch->setQuantity($quantity);
    $batch->setExpirationDate($expiration ?? new \DateTime('+1 year'));
    $batch->setCommissionPercent($commissionPercent);

    $this->em->persist($batch);

    $movement = new StockMovement();
    $movement->setProduct($product);
    $movement->setBatch($batch); // 🔥 importante
    $movement->setType(StockMovement::TYPE_IN);
    $movement->setQuantity($quantity);

    $this->em->persist($movement);

    $this->em->flush();
}
}