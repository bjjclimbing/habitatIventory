<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\Provider;
use App\Entity\Category;
use App\Entity\ProductCost;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:import:inventory',
    description: 'Import inventory from CSV'
)]
class ImportInventoryCommand extends Command
{
    protected static $defaultName = 'app:import:inventory';

    private array $providerCache = [];
    private array $categoryCache = [];
    private array $productCache = [];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'CSV file path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');

        if (!file_exists($file)) {
            $output->writeln('<error>File not found</error>');
            return Command::FAILURE;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        $i = 0;

        while (($row = fgetcsv($handle)) !== false) {

            $data = array_combine($header, $row);

            $sku = trim($data['CODIGO'] ?? '');
            $name = trim($data['PRODUCTO'] ?? '');
            $providerName = trim($data['MARCA'] ?? '');
            $procedureName = trim($data['PROCEDIMIENTO'] ?? '');
            $groupName = trim($data['GRUPO'] ?? '');
            $subGroupName = trim($data['SUBGRUPO'] ?? '');

            $directCost = (float) ($data['COSTO_DIRECTO'] ?? 0);
            $shippingCost = (float) ($data['ENVIO_NACIONALIZACION'] ?? 0);
            $totalCost = (float) ($data['COSTE_TOTAL'] ?? 0);

            if (!$sku || !$name) {
                continue;
            }

            // ======================
            // PROVIDER
            // ======================
            $providerKey = strtoupper($providerName);

            if (!isset($this->providerCache[$providerKey])) {
                $provider = $this->em->getRepository(Provider::class)
                    ->findOneBy(['name' => $providerKey]);

                if (!$provider) {
                    $provider = new Provider();
                    $provider->setName($providerName);
                    $this->em->persist($provider);
                }

                $this->providerCache[$providerKey] = $provider;
            }

            $provider = $this->providerCache[$providerKey];

            // ======================
            // CATEGORY TREE
            // ======================
            $procedure = $this->getOrCreateCategory($procedureName, null);
            $group = $this->getOrCreateCategory($groupName, $procedure);
            $subGroup = $this->getOrCreateCategory($subGroupName, $group);

            // ======================
            // PRODUCT
            // ======================
            if (!isset($this->productCache[$sku])) {
                $product = $this->em->getRepository(Product::class)
                    ->findOneBy(['sku' => $sku]);

                if (!$product) {
                    $product = new Product();
                    $product->setSku($sku);
                    $product->setName($name);
                    $product->setBrand($providerName); // 👈 AÑADIR ESTO

                    $product->setProvider($provider);
                    $product->setCategory($subGroup);
                    $product->setMinStock(10);
                    $this->em->persist($product);
                }

                $this->productCache[$sku] = $product;
            }

            $product = $this->productCache[$sku];

            // ======================
            // PRODUCT COST
            // ======================
            if ($totalCost > 0) {
                $cost = new ProductCost();
                $cost->setProduct($product);
                $cost->setDirectCost($directCost);
                $cost->setShippingCost($shippingCost);
                $cost->setTotalCost($totalCost);

                $this->em->persist($cost);
            }

            // ======================
            // BATCHING
            // ======================
            if ($i % 100 === 0) {
                $this->em->flush();
                $this->em->clear();

                // ⚠️ limpiar caches (muy importante)
                $this->providerCache = [];
                $this->categoryCache = [];
                $this->productCache = [];
            }

            $i++;
        }

        fclose($handle);

        $this->em->flush();

        $output->writeln("<info>Import finished: $i rows</info>");

        return Command::SUCCESS;
    }

    // ======================
    // CATEGORY HELPER
    // ======================
    private function getOrCreateCategory(string $name, ?Category $parent): ?Category
    {
        if (!$name) {
            return null;
        }

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