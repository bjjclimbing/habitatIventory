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
        $batches = $product->getBatches()->toArray();

        usort($batches, function ($a, $b) {
            return $a->getExpirationDate() <=> $b->getExpirationDate();
        });

        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $batchQty = $batch->getQuantity();

            if ($batchQty <= 0) continue;

            if ($batchQty >= $remaining) {
                $batch->setQuantity($batchQty - $remaining);
                $remaining = 0;
            } else {
                $remaining -= $batchQty;
                $batch->setQuantity(0);
            }
        }

        if ($remaining > 0) {
            throw new \Exception('Stock insuficiente');
        }

        // registrar movimiento
        $movement = new StockMovement();
        $movement->setProduct($product);
        $movement->setBatch($batch);
        $movement->setType(StockMovement::TYPE_OUT);
        $movement->setQuantity($quantity);

        $this->em->persist($movement);
        $this->em->flush();
    }

    // ======================
    // AÑADIR STOCK
    // ======================
    public function addStock(Product $product, int $quantity, ?\DateTime $expiration = null): void
    {
        $batch = new InventoryBatch();
        $batch->setProduct($product);
        $batch->setQuantity($quantity);
        $batch->setExpirationDate($expiration ?? new \DateTime('+1 year'));

        $movement = new StockMovement();
        $movement->setProduct($product);
        $movement->setType(StockMovement::TYPE_IN);
        $movement->setQuantity($quantity);

        $this->em->persist($batch);
        $this->em->persist($movement);
        $this->em->flush();
    }
}