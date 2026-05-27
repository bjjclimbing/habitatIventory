<?php

namespace App\Service;

use App\Entity\Valija;
use App\Entity\ValijaStock;
use App\Entity\ValijaMovement;
use App\Repository\ValijaProductRepository;
use App\Repository\ValijaStockRepository;
use App\Repository\InventoryBatchRepository;
use Doctrine\ORM\EntityManagerInterface;

class ValijaInitializerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValijaProductRepository $valijaProductRepo,
        private ValijaStockRepository $valijaStockRepo,
        private InventoryBatchRepository $batchRepo
    ) {}

    public function initialize(Valija $valija): void
    {
        $valijaProducts = $this->valijaProductRepo->findBy(['valija' => $valija]);

        foreach ($valijaProducts as $valijaProduct) {

            $product = $valijaProduct->getProduct();
            $required = $valijaProduct->getStockMin();

            // 🔹 cuánto hay ya en la valija
            $current = $this->valijaStockRepo->getTotalByValijaAndProduct($valija, $product);

            $missing = $required - $current;

            if ($missing <= 0) {
                continue;
            }

            // 🔥 FEFO: batches ordenados por caducidad
            $batches = $this->batchRepo->findAvailableBatchesByProductOrderByExpiry($product);

            foreach ($batches as $batch) {

                if ($missing <= 0) {
                    break;
                }

                $available = $batch->getQuantity();

                if ($available <= 0) {
                    continue;
                }

                $take = min($available, $missing);

                // 🔻 restar del batch
                $batch->decrease($take);

                // 🔺 añadir a valija
                $valijaStock = new ValijaStock();
                $valijaStock->setValija($valija);
                $valijaStock->setProduct($product);
                $valijaStock->setBatch($batch);
                $valijaStock->setQuantity($take);

                $this->em->persist($valijaStock);

                // 🧾 log (opcional pero recomendado)
                $movement = new ValijaMovement();
                $movement->setValija($valija);
                $movement->setProduct($product);
                $movement->setBatch($batch);
                $movement->setType(ValijaMovement::TYPE_REPLENISH);
                $movement->setQuantity($take);

                $this->em->persist($movement);

                $missing -= $take;
            }

            // 🚨 ALERTA si no pudiste llenar
            if ($missing > 0) {
                // aquí puedes lanzar evento / log / alerta
                dump("Falta stock para producto {$product->getName()}");
            }
        }

        $this->em->flush();
    }
}