<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class SalesCsvImporter
{
    public const MODE_STRICT = 'strict';

    public function __construct(
        private ProductRepository $productRepo,
        private StockService $stockService,
        private ValijaSyncService $valijaSyncService,
        private EntityManagerInterface $em
    ) {}

    public function import(string $filePath, string $mode = self::MODE_STRICT): void
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: $filePath");
        }

        $handle = fopen($filePath, 'r');

        // skip header
        fgetcsv($handle);

        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {

            $rowNumber++;

            [
                $sku,
                $quantity,
                $reference,
                $comments
            ] = array_pad($row, 4, null);

            // 🔹 limpieza
            $sku = strtoupper(trim($sku ?? ''));
            $quantity = (int) $quantity;

            if (!$sku) {
                throw new \RuntimeException("Row $rowNumber: SKU vacío");
            }

            if ($quantity <= 0) {
                throw new \RuntimeException("Row $rowNumber: quantity inválida");
            }

            // 🔎 buscar producto
            $product = $this->productRepo->findOneBy(['sku' => $sku]);

            if (!$product) {
                throw new \RuntimeException(
                    "Row $rowNumber: producto $sku no existe"
                );
            }

            // 🔴 consumir stock (FEFO)
            $this->stockService->consume($product, $quantity);

            // 🔥 disparar sync de valijas SOLO para este producto
            $this->valijaSyncService->syncAffectedValijas($product);
        }

        fclose($handle);

        $this->em->flush();
    }
}