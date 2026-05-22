<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\StockMovement;
use App\Entity\InventoryBatch;
use Doctrine\ORM\EntityManagerInterface;

class StockService
{
    public const MODE_INCREMENTAL = 'incremental';
    public const MODE_SYNC = 'sync';
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
    public function addStock(
        Product $product,
        int $quantity,
        ?\DateTime $expiration = null,
        ?float $commissionPercent = null
    ): void {
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
    

    public function addOrUpdateStock(
        Product $product,
        int $quantity,
        ?\DateTime $expiration = null,
        ?float $commissionPercent = null,
        string $mode = self::MODE_INCREMENTAL
    ): void {
    
        // ==========================
        // ❌ SIN FECHA → NO HACER NADA
        // ==========================
        if (!$expiration) {
            return;
        }
    
        // 🔥 normalizar fecha (clave)
        $expiration->setTime(0, 0, 0);
    
        // ==========================
        // SYNC MODE
        // ==========================
        if ($mode === self::MODE_SYNC) {
    
            $batches = $this->em->getRepository(InventoryBatch::class)
                ->findBy(['product' => $product]);
    
            foreach ($batches as $b) {
                $this->em->remove($b);
            }
    
            $this->em->flush();
        }
    
        // ==========================
        // BUSCAR BATCH EXISTENTE
        // ==========================
        $start = (clone $expiration)->setTime(0, 0, 0);
$end = (clone $expiration)->modify('+1 day')->setTime(0, 0, 0);
        $existingBatch = $this->em->getRepository(InventoryBatch::class)
    ->createQueryBuilder('b')
    ->where('b.product = :productId')
    ->andWhere('b.expirationDate >= :start')
    ->andWhere('b.expirationDate < :end')
    ->setParameter('productId', $product->getId())
    ->setParameter('start', $start)
    ->setParameter('end', $end)
    ->getQuery()
    ->getOneOrNullResult();
    
        // ==========================
        // UPDATE
        // ==========================
        if ($existingBatch) {
    
            $existingBatch->setQuantity(
                $existingBatch->getQuantity() + $quantity
            );
    
            return;
        }
    
        // ==========================
        // CREATE NEW BATCH
        // ==========================
        $batch = new InventoryBatch();
        $batch->setProduct($product);
        $batch->setQuantity($quantity);
        $batch->setExpirationDate($expiration);
        $batch->setCommissionPercent($commissionPercent);
    
        $this->em->persist($batch);
    }
}
