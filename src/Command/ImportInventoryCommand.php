<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\Provider;
use App\Entity\Category;
use App\Entity\ProductCost;
use App\Entity\Valija;
use App\Entity\ValijaProduct;
use App\Repository\ProductCostRepository;
use App\Service\StockService;
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
    description: 'Import inventory + valijas from XLSX'
)]
class ImportInventoryCommand extends Command
{
    private array $providerCache = [];
    private array $categoryCache = [];
    private array $productCache = [];
    private array $valijaCache = [];

    public function __construct(
        private EntityManagerInterface $em,
        private StockService $stockService,
        private ProductCostRepository $productCostRepo
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED);
        $this->addArgument('sheet', InputArgument::OPTIONAL, 'Sheet name', 'Andre');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $sheetName = $input->getArgument('sheet');

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getSheet(0);

        $rows = [];

        // ======================
        // LECTURA ROBUSTA
        // ======================
        foreach ($sheet->getRowIterator() as $row) {

            $data = [];

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {

                $value = $cell->getOldCalculatedValue();

                if ($value === null || (is_string($value) && str_starts_with($value, '='))) {
                    $value = $cell->getValue();
                }

                $data[] = $value;
            }

            $rows[] = $data;
        }

        // ======================
        // DETECTAR HEADER
        // ======================
        $headerRowIndex = null;

        foreach ($rows as $i => $row) {

            $joined = strtoupper(implode(' ', array_map(fn($v) => (string)$v, $row)));

            if (str_contains($joined, 'COD') && str_contains($joined, 'PROD')) {
                $headerRowIndex = $i;
                break;
            }
        }

        if ($headerRowIndex === null) {
            throw new \RuntimeException('Header not found');
        }

        // ======================
        // NORMALIZAR HEADERS
        // ======================
        $headers = array_map(function ($h) {
            $h = strtoupper(trim((string)$h));
            $h = str_replace(["\n", "\r"], ' ', $h);
            return preg_replace('/\s+/', ' ', $h);
        }, $rows[$headerRowIndex]);

        // ======================
        // MAP DINÁMICO
        // ======================
        $map = [];

        foreach ($headers as $h) {

            if (str_contains($h, 'COD')) $map['sku'] = $h;
            if (str_contains($h, 'PROD')) $map['name'] = $h;
            if (str_contains($h, 'MARCA')) $map['brand'] = $h;
            if (str_contains($h, 'SUBGRUPO')) $map['category'] = $h;
            if (str_contains($h, 'INVENTARIO') || str_contains($h, 'STOCK')) $map['stock'] = $h;
            if (str_contains($h, 'FECHA')) $map['date'] = $h;
            if (str_contains($h, 'DESCRIP')) $map['description'] = $h; // 🔥 NUEVO
        }

        // ======================
        // VALIJAS
        // ======================
        $valijaColumns = [];

        foreach ($headers as $index => $header) {
            if (str_starts_with($header, 'VALIJA:') || str_starts_with($header, 'MALETA:')) {
                $valijaColumns[$index] = trim(str_replace(['VALIJA:', 'MALETA:'], '', $header));
            }
        }

        $processed = 0;

        // ======================
        // LOOP
        // ======================
        for ($r = $headerRowIndex + 1; $r < count($rows); $r++) {

            $row = $rows[$r];

            if (!array_filter($row)) continue;

            $data = [];

            foreach ($headers as $i => $col) {
                $data[$col] = $row[$i] ?? null;
            }

            // ======================
            // PRODUCTO
            // ======================
            $sku = strtoupper(trim((string)($data[$map['sku'] ?? ''] ?? '')));
            $name = trim((string)($data[$map['name'] ?? ''] ?? ''));

            if (!$sku || !$name) continue;

            $provider = $this->getProvider($data[$map['brand'] ?? ''] ?? 'UNKNOWN');
            $category = $this->getCategory($data[$map['category'] ?? ''] ?? 'GENERAL', null);

            $product = $this->getProduct($sku, $name, $provider, $category);

            // ======================
            // 🔥 DESCRIPCIÓN
            // ======================
            $description = trim((string)($data[$map['description'] ?? ''] ?? ''));

            if ($description !== '' && $product->getDescription() !== $description) {
                $product->setDescription($description);
            }

            // ======================
            // COSTE
            // ======================
            $total = (float)($data['COSTE TOTAL'] ?? $data['PRECIO'] ?? 0);

            if ($total > 0) {
                $cost = new ProductCost();
                $cost->setProduct($product);
                $cost->setTotalCost($total);
                $cost->setDirectCost($total);
                $cost->setShippingCost(0);
                $this->em->persist($cost);
            }

            // ======================
            // FECHA
            // ======================
            $rawDate = $data[$map['date'] ?? ''] ?? null;

            if (is_string($rawDate) && str_starts_with($rawDate, '=')) {
                $rawDate = null;
            }

            $expiration = null;

            if ($rawDate !== null && $rawDate !== '') {
                try {
                    if (is_numeric($rawDate)) {
                        $expiration = ExcelDate::excelToDateTimeObject($rawDate);
                    } else {
                        $expiration = new \DateTime($rawDate);
                    }
                } catch (\Exception) {}
            }

            // ======================
            // STOCK
            // ======================
            $stock = (int)($data[$map['stock'] ?? ''] ?? 0);

            if ($stock > 0 && $expiration) {

                $this->stockService->addOrUpdateStock(
                    $product,
                    $stock,
                    $expiration,
                    null,
                    StockService::MODE_INCREMENTAL
                );

            } else {
                $output->writeln("⚠️ SKIP batch sin fecha → $sku");
            }

            // ======================
            // VALIJAS
            // ======================
            foreach ($valijaColumns as $colIndex => $valijaName) {

                $value = (int)($row[$colIndex] ?? 0);

                if ($value <= 0) continue;

                $valija = $this->getOrCreateValija($valijaName);

                $this->assignProductToValija($valija, $product, $value);
            }

            // ======================
            // BATCHING
            // ======================
            if ($processed > 0 && $processed % 200 === 0) {
                $this->em->flush();
                $this->em->clear();

                $this->providerCache = [];
                $this->categoryCache = [];
                $this->productCache = [];
                $this->valijaCache = [];
            }

            $processed++;
        }

        $this->em->flush();

        $output->writeln("Importados: $processed");

        return Command::SUCCESS;
    }

    // ======================
    // HELPERS
    // ======================

    private function getProvider(string $name): Provider
    {
        $key = strtoupper($name);

        if (!isset($this->providerCache[$key])) {
            $repo = $this->em->getRepository(Provider::class);
            $p = $repo->findOneBy(['name' => $key]) ?? new Provider();

            $p->setName($name);
            $this->em->persist($p);

            $this->providerCache[$key] = $p;
        }

        return $this->providerCache[$key];
    }

    private function getCategory(string $name, ?Category $parent): Category
    {
        $key = strtoupper($name);

        if (!isset($this->categoryCache[$key])) {
            $repo = $this->em->getRepository(Category::class);
            $c = $repo->findOneBy(['name' => $key]) ?? new Category();

            $c->setName($name);
            $c->setParent($parent);
            $this->em->persist($c);

            $this->categoryCache[$key] = $c;
        }

        return $this->categoryCache[$key];
    }

    private function getProduct(string $sku, string $name, Provider $provider, ?Category $category): Product
    {
        if (!isset($this->productCache[$sku])) {
            $repo = $this->em->getRepository(Product::class);
            $p = $repo->findOneBy(['sku' => $sku]) ?? new Product();

            $p->setSku($sku);
            $p->setName($name);
            $p->setBrand($provider->getName());
            $p->setProvider($provider);
            $p->setCategory($category);
            $p->setMinStock(10);

            $this->em->persist($p);

            $this->productCache[$sku] = $p;
        }

        return $this->productCache[$sku];
    }

    private function getOrCreateValija(string $name): Valija
    {
        $key = strtoupper($name);

        if (!isset($this->valijaCache[$key])) {
            $repo = $this->em->getRepository(Valija::class);
            $v = $repo->findOneBy(['name' => $name]) ?? new Valija();

            $v->setName($name);
            $this->em->persist($v);

            $this->valijaCache[$key] = $v;
        }

        return $this->valijaCache[$key];
    }

    private function assignProductToValija(Valija $valija, Product $product, int $stockMin): void
    {
        $repo = $this->em->getRepository(ValijaProduct::class);

        $vp = $repo->findOneBy([
            'valija' => $valija,
            'product' => $product
        ]);

        if (!$vp) {
            $vp = new ValijaProduct();
            $vp->setValija($valija);
            $vp->setProduct($product);
            $this->em->persist($vp);
        }

        $vp->setStockMin(max(0, $stockMin));
    }
}