<?php

namespace App\Service;

use App\Repository\ProductRepository;
use App\Repository\ValijaProductRepository;
use App\Repository\ValijaStockRepository;

class AlertService
{
    public function __construct(
        private ProductRepository $productRepository,
        private ValijaProductRepository $valijaProductRepo,
        private ValijaStockRepository $valijaStockRepo
    ) {}

    // =====================================
    // 🔹 ALERTAS PLANAS (opcional)
    // =====================================
    public function getAlerts(): array
    {
        $alerts = [];

        $products = $this->productRepository->findAll();

        foreach ($products as $product) {

            // 🔴 STOCK BAJO GLOBAL
            if ($product->getStock() <= $product->getMinStock()) {
                $alerts[] = [
                    'type' => 'low_stock',
                    'product' => $product,
                    'message' => 'Stock bajo'
                ];
            }

            foreach ($product->getBatches() as $batch) {

                if (!$batch->getExpirationDate()) {
                    continue;
                }

                $now = new \DateTime();
                $days = $now->diff($batch->getExpirationDate())->days;

                // 🔥 CADUCADO
                if ($batch->getExpirationDate() < $now) {
                    $alerts[] = [
                        'type' => 'expired',
                        'product' => $product,
                        'batch' => $batch,
                        'message' => 'Lote caducado'
                    ];
                }

                // ⚠️ PRÓXIMO A CADUCAR
                elseif ($days <= 7) {
                    $alerts[] = [
                        'type' => 'warning',
                        'product' => $product,
                        'batch' => $batch,
                        'message' => 'Próximo a caducar'
                    ];
                }
            }
        }

        return $alerts;
    }

    // =====================================
    // 🔥 ALERTAS AGRUPADAS (USADA POR API)
    // =====================================
    public function getAlertsGrouped(): array
    {
        $grouped = [
            'low_stock' => [],
            'warning' => [],
            'expired' => [],
            'valija_low' => [],
            'valija_critical' => [],
            'valija_expiring' => [],
            'valija_expired' => []
        ];

        $products = $this->productRepository->findAll();

        // =====================================
        // 🔹 ALERTAS DE INVENTARIO GLOBAL
        // =====================================
        foreach ($products as $product) {

            // 🔴 STOCK BAJO
            if ($product->getStock() <= $product->getMinStock()) {
                $grouped['low_stock'][] = $product;
            }

            foreach ($product->getBatches() as $batch) {

                if (!$batch->getExpirationDate()) {
                    continue;
                }

                $now = new \DateTime();
                $days = $now->diff($batch->getExpirationDate())->days;

                // 🔥 CADUCADO
                if ($batch->getExpirationDate() < $now) {
                    $grouped['expired'][] = [
                        'product' => $product,
                        'batch' => $batch
                    ];
                }

                // ⚠️ PRÓXIMO A CADUCAR (6 meses)
                elseif ($days <= 182) {
                    $grouped['warning'][] = [
                        'product' => $product,
                        'batch' => $batch
                    ];
                }
            }
        }

        // =====================================
        // 🔥 ALERTAS DE VALIJAS
        // =====================================
        $valijaProducts = $this->valijaProductRepo->findAll();

        foreach ($valijaProducts as $vp) {

            $valija = $vp->getValija();
            $product = $vp->getProduct();
            $min = $vp->getStockMin();

            // 🔹 stock actual en valija
            $current = $this->valijaStockRepo
                ->getTotalByValijaAndProduct($valija, $product);

            // ⚠️ si está por debajo del mínimo
            if ($current < $min) {

                // 🔥 CRÍTICO: no hay stock global
                if ($product->getStock() <= 0) {

                    $grouped['valija_critical'][] = [
                        'valija' => $valija,
                        'product' => $product,
                        'current' => $current,
                        'min' => $min
                    ];

                } else {

                    // ⚠️ puede reponerse
                    $grouped['valija_low'][] = [
                        'valija' => $valija,
                        'product' => $product,
                        'current' => $current,
                        'min' => $min
                    ];
                }
            }
        }
// =====================================
// 🔥 PRODUCTOS EN VALIJAS PRÓXIMOS A CADUCAR Y CADUCADOS
// =====================================
$now = new \DateTime();

foreach ($this->valijaStockRepo->findAll() as $stock) {

    if ($stock->getQuantity() <= 0) {
        continue;
    }

    $batch = $stock->getBatch();

    if (!$batch->getExpirationDate()) {
        continue;
    }

    $expirationDate = $batch->getExpirationDate();

    $days = $now->diff($expirationDate)->days;

    // 🔥 YA CADUCADO
    if ($expirationDate < $now) {

        $grouped['valija_expired'][] = [
            'valija' => $stock->getValija(),
            'product' => $stock->getProduct(),
            'batch' => $batch,
            'current' => $stock->getQuantity(),
            'days' => 0
        ];

        continue;
    }

    // ⚠️ PRÓXIMO A CADUCAR
    if ($days <= 182) {

        $grouped['valija_expiring'][] = [
            'valija' => $stock->getValija(),
            'product' => $stock->getProduct(),
            'batch' => $batch,
            'current' => $stock->getQuantity(),
            'days' => $days
        ];
    }
}
        return $grouped;
    }
}