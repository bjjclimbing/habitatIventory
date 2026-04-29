<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\Provider;
use App\Entity\Category;
use App\Entity\ProductCost;
use App\Repository\ProductRepository;
use App\Repository\ProductCostRepository;
use Doctrine\ORM\EntityManagerInterface;

class PurchaseCsvImporter
{
    public const MODE_STRICT = 'strict';
    public const MODE_CREATE = 'create';

    private array $providerCache = [];
    private array $categoryCache = [];
    private array $productCache = [];

    public function __construct(
        private ProductRepository $productRepo,
        private ProductCostRepository $productCostRepo,
        private StockService $stockService,
        private EntityManagerInterface $em
    ) {}

    public function import(string $filePath, string $mode = self::MODE_CREATE): void
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: $filePath");
        }

        if (!in_array($mode, [self::MODE_STRICT, self::MODE_CREATE])) {
            throw new \RuntimeException("Invalid mode: $mode");
        }

        $handle = fopen($filePath, 'r');

        // skip header
        fgetcsv($handle);

        $rowNumber = 1;
        $createdProducts = [];

        while (($row = fgetcsv($handle)) !== false) {

            $rowNumber++;

            [
                $sku,
                $name,
                $brand,
                $providerName,
                $procedureName,
                $groupName,
                $subGroupName,
                $quantity,
                $expirationDate,
                $costDirect,
                $costShipping,
                $costTotal
            ] = array_pad($row, 12, null);

            // 🔹 limpieza
            $sku = strtoupper(trim($sku ?? ''));
            $name = trim($name ?? '');
            $brand = trim($brand ?? '');
            $providerName = trim($providerName ?? '');
            $procedureName = trim($procedureName ?? '');
            $groupName = trim($groupName ?? '');
            $subGroupName = trim($subGroupName ?? '');
            $quantity = (int) $quantity;

            if (!$sku) {
                throw new \RuntimeException("Row $rowNumber: SKU vacío");
            }

            if ($quantity <= 0) {
                throw new \RuntimeException("Row $rowNumber: quantity inválida");
            }

            // ===== Provider =====
            $provider = $this->getProvider($providerName ?: 'UNKNOWN');

            // ===== Category =====
            $procedure = $this->getCategory($procedureName ?: 'GENERAL', null);
            $group = $this->getCategory($groupName ?: 'GENERAL', $procedure);
            $subGroup = $this->getCategory($subGroupName ?: 'GENERAL', $group);

            // ===== Product =====
            $product = $this->productRepo->findOneBy(['sku' => $sku]);

            if (!$product) {

                if ($mode === self::MODE_STRICT) {
                    throw new \RuntimeException(
                        "Row $rowNumber: producto $sku no existe (modo STRICT)"
                    );
                }

                if (!$name) {
                    throw new \RuntimeException(
                        "Row $rowNumber: producto $sku sin nombre"
                    );
                }

                $product = new Product();
                $product->setSku($sku);
                $product->setName($name);
                $product->setBrand($brand ?: $provider->getName());
                $product->setProvider($provider);
                $product->setCategory($subGroup);
                $product->setMinStock(10);

                $this->em->persist($product);

                $createdProducts[] = $sku;
            }

            // ===== COSTES =====
            $direct = (float) ($costDirect ?? 0);
            $shipping = (float) ($costShipping ?? 0);
            $total = (float) ($costTotal ?? 0);

            if ($total <= 0) {
                $total = $direct + $shipping;
            }

            if ($total > 0) {

                $lastCost = $this->productCostRepo->findLastCost($product);

                if (
                    !$lastCost ||
                    $lastCost->getTotalCost() != $total ||
                    $lastCost->getDirectCost() != $direct ||
                    $lastCost->getShippingCost() != $shipping
                ) {
                    $cost = new ProductCost();
                    $cost->setProduct($product);
                    $cost->setDirectCost($direct);
                    $cost->setShippingCost($shipping);
                    $cost->setTotalCost($total);

                    $this->em->persist($cost);
                }
            }

            // ===== Expiration =====
            $expiration = null;

            if (!empty($expirationDate)) {
                try {
                    $expiration = new \DateTime($expirationDate);
                } catch (\Exception $e) {
                    throw new \RuntimeException("Row $rowNumber: fecha inválida");
                }
            }

            // ===== Stock =====
            $this->stockService->addStock(
                $product,
                $quantity,
                $expiration
            );
        }

        fclose($handle);

        $this->em->flush();

        if (!empty($createdProducts)) {
            dump("Productos creados:", $createdProducts);
        }
    }

    // ================= HELPERS =================

    private function getProvider(string $name): Provider
    {
        $key = strtoupper($name);

        if (!isset($this->providerCache[$key])) {

            $repo = $this->em->getRepository(Provider::class);

            $provider = $repo->findOneBy(['name' => $key]);

            if (!$provider) {
                $provider = new Provider();
                $provider->setName($name);
                $this->em->persist($provider);
            }

            $this->providerCache[$key] = $provider;
        }

        return $this->providerCache[$key];
    }

    private function getCategory(string $name, ?Category $parent): ?Category
    {
        if ($name === '') return null;

        $key = strtoupper($name) . '_' . ($parent?->getId() ?? 'root');

        if (!isset($this->categoryCache[$key])) {

            $repo = $this->em->getRepository(Category::class);

            $category = $repo->findOneBy([
                'name' => strtoupper($name),
                'parent' => $parent
            ]);

            if (!$category) {
                $category = new Category();
                $category->setName($name);
                $category->setParent($parent);
                $this->em->persist($category);
            }

            $this->categoryCache[$key] = $category;
        }

        return $this->categoryCache[$key];
    }
}