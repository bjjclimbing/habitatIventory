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

class ValijaSyncService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValijaProductRepository $valijaProductRepo,
        private ValijaStockRepository $valijaStockRepo,
        private InventoryBatchRepository $batchRepo
    ) {}

    public function sync(Valija $valija): void
    {
        $this->em->beginTransaction();

        try {

            $valijaProducts = $this->valijaProductRepo->findBy(['valija' => $valija]);

            foreach ($valijaProducts as $valijaProduct) {

                $product = $valijaProduct->getProduct();
                $stockMin = $valijaProduct->getStockMin();

                // 🔹 stock actual en valija
                $currentStock = $this->valijaStockRepo->getTotalByValijaAndProduct($valija, $product);

                $missing = $stockMin - $currentStock;

                if ($missing <= 0) {
                    continue;
                }

                // 🔥 batches FEFO
                $batches = $this->batchRepo->findAvailableBatchesByProductOrderByExpiry($product);

                foreach ($batches as $batch) {

                    if ($missing <= 0) {
                        break;
                    }

                    // 🔒 lock para evitar concurrencia
                    $this->em->lock($batch, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);

                    $available = $batch->getQuantity();

                    if ($available <= 0) {
                        continue;
                    }

                    $take = min($available, $missing);

                    // 🔻 restar batch
                    $batch->decrease($take);

                    // 🔎 buscar si ya existe ese batch en la valija
                    $existingStock = $this->valijaStockRepo->findOneBy([
                        'valija' => $valija,
                        'product' => $product,
                        'batch' => $batch
                    ]);

                    if ($existingStock) {
                        $existingStock->increase($take);
                    } else {
                        $valijaStock = new ValijaStock();
                        $valijaStock->setValija($valija);
                        $valijaStock->setProduct($product);
                        $valijaStock->setBatch($batch);
                        $valijaStock->setQuantity($take);

                        $this->em->persist($valijaStock);
                    }

                    // 🧾 auditoría
                    $movement = new ValijaMovement();
                    $movement->setValija($valija);
                    $movement->setProduct($product);
                    $movement->setBatch($batch);
                    $movement->setType(ValijaMovement::TYPE_REPLENISH);
                    $movement->setQuantity($take);

                    $this->em->persist($movement);

                    $missing -= $take;
                }

                // 🚨 ALERTA si no se pudo completar
                if ($missing > 0) {
                    // aquí puedes integrar notificación real
                    dump("⚠️ Falta stock para {$product->getName()} en valija {$valija->getName()}");
                }
            }

            $this->em->flush();
            $this->em->commit();

        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }
    public function syncAffectedValijas(Product $product): void
{
    $valijaProducts = $this->valijaProductRepo->findByProduct($product);

    foreach ($valijaProducts as $vp) {
        $this->sync($vp->getValija());
    }
}
}