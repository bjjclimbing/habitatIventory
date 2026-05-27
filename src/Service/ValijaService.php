<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\Valija;
use App\Entity\ValijaMovement;
use App\Entity\ValijaStock;
use App\Repository\InventoryBatchRepository;
use App\Repository\ValijaProductRepository;
use App\Repository\ValijaStockRepository;
use Doctrine\ORM\EntityManagerInterface;

class ValijaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValijaStockRepository $valijaStockRepo,
        private ValijaSyncService $syncService
    ) {}

    public function consumeFromValija(
        Valija $valija,
        Product $product,
        int $quantity
    ): void {
    
        $this->em->beginTransaction();
    
        try {
    
            $stocks = $this->valijaStockRepo->findBy([
                'valija' => $valija,
                'product' => $product
            ]);
    
            $remaining = $quantity;
    
            foreach ($stocks as $stock) {
    
                if ($remaining <= 0) break;
    
                $available = $stock->getQuantity();
    
                if ($available <= 0) continue;
    
                $take = min($available, $remaining);
    
                // 🔻 bajar stock valija
                $stock->decrease($take);
    
                // 🧾 REGISTRAR CONSUMO
                $movement = new ValijaMovement();
                $movement->setValija($valija);
                $movement->setProduct($product);
                $movement->setBatch($stock->getBatch());
                $movement->setType(ValijaMovement::TYPE_CONSUME);
                $movement->setQuantity($take);
    
                $this->em->persist($movement);
    
                $remaining -= $take;
            }
    
            if ($remaining > 0) {
                throw new \RuntimeException("Stock insuficiente en valija");
            }
    
            // 🔥 REPOSICIÓN AUTOMÁTICA
            $this->syncService->sync($valija);
    
            $this->em->flush();
            $this->em->commit();
    
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }
}