<?php

namespace App\Service;

use App\Repository\ProductRepository;

class AlertService
{
    public function __construct(
        private ProductRepository $productRepository
    ) {}

    public function getAlerts(): array
    {
        $alerts = [];

        $products = $this->productRepository->findAll();

        foreach ($products as $product) {

            // 🔴 STOCK BAJO
            if ($product->getStock() <= $product->getMinStock()) {
                $alerts[] = [
                    'type' => 'low_stock',
                    'product' => $product,
                    'message' => 'Stock bajo'
                ];
            }

            foreach ($product->getBatches() as $batch) {

                if (!$batch->getExpirationDate()) continue;

                $days = (new \DateTime())->diff($batch->getExpirationDate())->days;

                // 🔥 CADUCADO
                if ($batch->getExpirationDate() < new \DateTime()) {
                    $alerts[] = [
                        'type' => 'expired',
                        'product' => $product,
                        'batch' => $batch,
                        'message' => 'Lote caducado'
                    ];
                }

                // ⚠️ PRÓXIMO A CADUCAR (<7 días)
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
    public function getAlertsGrouped(): array
{
    $grouped = [
        'low_stock' => [],
        'warning' => [],
        'expired' => [],
    ];

    $products = $this->productRepository->findAll();

    foreach ($products as $product) {

        // 🔴 STOCK BAJO
        if ($product->getStock() <= $product->getMinStock()) {
            $grouped['low_stock'][] = $product;
        }

        foreach ($product->getBatches() as $batch) {

            if (!$batch->getExpirationDate()) continue;

            $now = new \DateTime();
            $days = $now->diff($batch->getExpirationDate())->days;

            if ($batch->getExpirationDate() < $now) {
                $grouped['expired'][] = [
                    'product' => $product,
                    'batch' => $batch
                ];
            } elseif ($days <= 7) {
                $grouped['warning'][] = [
                    'product' => $product,
                    'batch' => $batch
                ];
            }
        }
    }

    return $grouped;
}
}