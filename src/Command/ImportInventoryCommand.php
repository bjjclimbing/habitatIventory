<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\Provider;
use App\Entity\Category;
use App\Entity\ProductCost;
use App\Entity\InventoryBatch;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:import:inventory-xlsx',
    description: 'Import inventory directly from XLSX (no formula evaluation)'
)]
class ImportInventoryCommand extends Command
{
    private array $providerCache = [];
    private array $categoryCache = [];
    private array $productCache = [];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'XLSX file path');
        $this->addArgument('sheet', InputArgument::OPTIONAL, 'Sheet name', 'costos-venta detallado');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $sheetName = $input->getArgument('sheet');

        if (!is_file($file)) {
            $output->writeln('<error>File not found</error>');
            return Command::FAILURE;
        }

        // 🔥 Cargar Excel
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getSheet(0);

        // 🔥 Leer filas SIN evaluar fórmulas (clave)
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $rowData = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue(); // 👈 valor crudo
            }
            $rows[] = $rowData;
        }

        // 🔍 Detectar cabecera (fila donde está CODIGO)
        $headerRowIndex = null;
        foreach ($rows as $i => $row) {
            $upper = array_map(fn($v) => strtoupper(trim((string)$v)), $row);
            if (in_array('CODIGO', $upper, true)) {
                $headerRowIndex = $i;
                break;
            }
        }

        if ($headerRowIndex === null) {
            $output->writeln('<error>Header row not found (CODIGO)</error>');
            return Command::FAILURE;
        }

        // 🧠 Normalizar cabeceras
        $headers = array_map(function ($h) {
            $h = strtoupper(trim((string)$h));
            $h = str_replace(["\n", "\r"], ' ', $h);
            $h = preg_replace('/\s+/', ' ', $h);
            return $h;
        }, $rows[$headerRowIndex]);

        // Mapear nombres "sucios" → nombres esperados
        $map = [
            'COSTO DIRECTO' => 'COSTO_DIRECTO',
            'ENVIO Y NACIONALIZACION' => 'ENVIO_NACIONALIZACION',
            'COSTO TOTAL' => 'COSTE_TOTAL',
            'FECHA VENCIMIENTO' => 'FECHA_VENCIMIENTO',
        ];

        $normalizedHeaders = [];
        foreach ($headers as $h) {
            $normalizedHeaders[] = $map[$h] ?? $h;
        }

        $processed = 0;

        // 🔁 Iterar filas de datos
        for ($r = $headerRowIndex + 1; $r < count($rows); $r++) {

            $row = $rows[$r];

            // Evitar filas vacías
            if (!array_filter($row, fn($v) => $v !== null && $v !== '')) {
                continue;
            }

            // Combinar cabecera + fila
            $data = [];
            foreach ($normalizedHeaders as $idx => $colName) {
                $data[$colName] = $row[$idx] ?? null;
            }

            // 🔹 Campos base
            $sku = trim((string)($data['CODIGO'] ?? ''));
            $name = trim((string)($data['PRODUCTO'] ?? ''));
            $providerName = trim((string)($data['MARCA'] ?? ''));
            $procedureName = trim((string)($data['PROCEDIMIENTO'] ?? ''));
            $groupName = trim((string)($data['GRUPO'] ?? ''));
            $subGroupName = trim((string)($data['SUBGRUPO'] ?? ''));

            if ($sku === '' || $name === '') {
                $output->writeln("<comment>Skipping row {$r}: missing CODIGO/PRODUCTO</comment>");
                continue;
            }

            // 🔹 Costes
            $directCost = (float)($data['COSTO_DIRECTO'] ?? 0);
            $shippingCost = (float)($data['ENVIO_NACIONALIZACION'] ?? 0);
            $totalCost = (float)($data['COSTE_TOTAL'] ?? 0);

            // 🔹 Stock + caducidad
            $stock = (int)($data['EXISTENCIA'] ?? 0);
            $expirationRaw = $data['FECHA_VENCIMIENTO'] ?? null;

            // ===== Provider =====
            $provider = $this->getProvider($providerName ?: 'UNKNOWN');

            // ===== Category tree (3 niveles) =====
            $procedure = $this->getCategory($procedureName ?: 'GENERAL', null);
            $group = $this->getCategory($groupName ?: 'GENERAL', $procedure);
            $subGroup = $this->getCategory($subGroupName ?: 'GENERAL', $group);

            // ===== Product =====
            $product = $this->getProduct($sku, $name, $provider, $subGroup);

            // ===== ProductCost (histórico) =====
            if ($totalCost > 0 || $directCost > 0 || $shippingCost > 0) {
                $cost = new ProductCost();
                $cost->setProduct($product);
                $cost->setDirectCost($directCost);
                $cost->setShippingCost($shippingCost);
                $cost->setTotalCost($totalCost > 0 ? $totalCost : ($directCost + $shippingCost));
                $this->em->persist($cost);
            }

            // ===== InventoryBatch (stock + caducidad) =====
            if ($stock > 0) {

                // 🔥 sincronizar: eliminar batches previos
                foreach ($product->getBatches() as $b) {
                    $this->em->remove($b);
                }

                $expirationDate = null;

                if ($expirationRaw !== null && $expirationRaw !== '') {
                    try {
                        // Excel serial (número)
                        if (is_numeric($expirationRaw)) {
                            $expirationDate = ExcelDate::excelToDateTimeObject($expirationRaw);
                        } else {
                            // string (YYYY-MM-DD recomendado)
                            $expirationDate = new \DateTime((string)$expirationRaw);
                        }
                    } catch (\Exception $e) {
                        $expirationDate = null;
                    }
                }

                $batch = new InventoryBatch();
                $batch->setProduct($product);
                $batch->setQuantity($stock);
                $batch->setExpirationDate($expirationDate);
                $this->em->persist($batch);
            }

            // ===== Batching (memoria) =====
            if ($processed > 0 && $processed % 100 === 0) {
                $this->em->flush();
                $this->em->clear();

                // limpiar caches (entidades se desasocian tras clear)
                $this->providerCache = [];
                $this->categoryCache = [];
                $this->productCache = [];
            }

            $processed++;
        }

        $this->em->flush();

        $output->writeln("<info>Import finished: {$processed} rows</info>");
        return Command::SUCCESS;
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

            $cat = $repo->findOneBy([
                'name' => strtoupper($name),
                'parent' => $parent
            ]);

            if (!$cat) {
                $cat = new Category();
                $cat->setName($name);
                $cat->setParent($parent);
                $this->em->persist($cat);
            }

            $this->categoryCache[$key] = $cat;
        }

        return $this->categoryCache[$key];
    }

    private function getProduct(string $sku, string $name, Provider $provider, ?Category $category): Product
    {
        if (!isset($this->productCache[$sku])) {
            $repo = $this->em->getRepository(Product::class);
            $product = $repo->findOneBy(['sku' => $sku]);

            if (!$product) {
                $product = new Product();
                $product->setSku($sku);
                $product->setName($name);
                $product->setBrand($provider->getName()); // evita null
                $product->setProvider($provider);
                $product->setCategory($category);
                $product->setMinStock(10);
                $this->em->persist($product);
            }

            $this->productCache[$sku] = $product;
        }

        return $this->productCache[$sku];
    }
}