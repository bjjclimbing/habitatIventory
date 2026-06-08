<?php

namespace App\Service;

use App\Repository\InventoryBatchRepository;
use App\Repository\ProductCostRepository;
use App\Repository\ProductRepository;

class InventoryExportService
{
    public function __construct(
        private InventoryBatchRepository $batchRepository,
        private ProductRepository $productRepository,
        private ProductCostRepository $productCostRepository
    ) {
    }

    public function generateCsv(): string
    {
        $filename = sys_get_temp_dir() . '/inventory_' . date('Ymd_His') . '.csv';

        $handle = fopen($filename, 'w');

        // Cabecera EXACTAMENTE igual al importador
        fputcsv($handle, [
            'SKU',
            'PRODUCT_NAME',
            'BRAND',
            'PROVIDER',
            'PROCEDURE',
            'GROUP',
            'SUBGROUP',
            'QUANTITY',
            'EXPIRATION_DATE',
            'COST_DIRECT',
            'COST_SHIPPING',
            'COST_TOTAL',
            'PR_RVN'
        ]);

        // =====================================
// LOTES CON STOCK
// =====================================

$batches = $this->batchRepository->findAll();

foreach ($batches as $batch) {

    if ($batch->getQuantity() <= 0) {
        continue;
    }

    $product = $batch->getProduct();

    $subGroup = $product->getCategory();
    $group = $subGroup?->getParent();
    $procedure = $group?->getParent();

    $lastCost = $this->productCostRepository
        ->findLastCostByProduct($product);

    fputcsv($handle, [

        $product->getSku(),
        $product->getName(),
        $product->getBrand(),
        $product->getProvider()?->getName(),

        $procedure?->getName(),
        $group?->getName(),
        $subGroup?->getName(),

        $batch->getQuantity(),

        $batch->getExpirationDate()
            ? $batch->getExpirationDate()->format('Y-m-d')
            : '',

        $lastCost?->getDirectCost(),
        $lastCost?->getShippingCost(),
        $lastCost?->getTotalCost(),

        $batch->getCommissionPercent(),
    ]);
}

// =====================================
// PRODUCTOS AGOTADOS
// =====================================

$products = $this->productRepository->findAll();

foreach ($products as $product) {

    if ($product->getStock() > 0) {
        continue;
    }

    $subGroup = $product->getCategory();
    $group = $subGroup?->getParent();
    $procedure = $group?->getParent();

    $lastCost = $this->productCostRepository
        ->findLastCostByProduct($product);

    fputcsv($handle, [

        $product->getSku(),
        $product->getName(),
        $product->getBrand(),
        $product->getProvider()?->getName(),

        $procedure?->getName(),
        $group?->getName(),
        $subGroup?->getName(),

        0,          // quantity
        '',         // expiration

        $lastCost?->getDirectCost(),
        $lastCost?->getShippingCost(),
        $lastCost?->getTotalCost(),

        '',         // comisión desconocida
    ]);
}

        fclose($handle);

        return $filename;
    }
}