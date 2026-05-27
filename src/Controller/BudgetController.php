<?php

namespace App\Controller;

use App\Entity\Budget;
use App\Entity\BudgetItem;
use App\Entity\Client;
use App\Repository\ProductCostRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Dompdf\Dompdf;
use Monolog\Logger;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class BudgetController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepo,
        private ProductCostRepository $productCostRepo,
        private EntityManagerInterface $em,
    ) {
    }

    // =========================
    // CREATE
    // =========================
    #[Route('/api/budgets', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $budget = new Budget();
        $budget->setName($data['name'] ?? 'Presupuesto');

        // 🔥 CLIENTE
        $client = $this->em->getRepository(Client::class)
            ->find($data['clientId'] ?? null);

        $budget->setClient($client);

        foreach ($data['items'] ?? [] as $i) {

            $product = $this->productRepo->find($i['productId']);
            if (!$product) continue;

            $lastCost = $this->productCostRepo
                ->findLastCostByProduct($product);

            $originalPrice = $lastCost?->getTotalCost() ?? 0;

            $item = new BudgetItem();
            $item->setProduct($product);
            $item->setQuantity($i['quantity']);

            $item->setUnitPrice($originalPrice);

            $postedPrice = (float)($i['unitPrice'] ?? $originalPrice);
            $item->setCustomUnitPrice($postedPrice);

            $item->setPriceModificationReason(
                $i['priceModificationReason'] ?? null
            );

            $item->calculateTotal();

            $budget->addItem($item);
        }

        $this->em->persist($budget);
        $this->em->flush();

        return $this->json([
            'id' => $budget->getId()
        ]);
    }

    // =========================
    // LIST
    // =========================
    #[Route('/api/budgets', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $search = $request->query->get('search');

        $qb = $this->em->getRepository(Budget::class)
            ->createQueryBuilder('b')
            ->leftJoin('b.client', 'c')
            ->addSelect('c')
            ->orderBy('b.createdAt', 'DESC');

        if ($search) {
            $qb->andWhere('LOWER(b.name) LIKE :search')
               ->setParameter('search', '%' . strtolower($search) . '%');
        }

        $budgets = $qb->getQuery()->getResult();

        $data = [];

        foreach ($budgets as $b) {
            $data[] = [
                'id' => $b->getId(),
                'name' => $b->getName(),
                'client' => $b->getClient()?->getName(),
                'createdAt' => $b->getCreatedAt()->format('Y-m-d H:i:s'),
                'total' => $b->getTotal(),
                'itemsCount' => $b->getItems()->count(),
            ];
        }

        return $this->json($data);
    }

    // =========================
    // DETAIL
    // =========================
    #[Route('/api/budgets/{id}', methods: ['GET'])]
    public function detail(Budget $budget): JsonResponse
    {
        $items = [];

        foreach ($budget->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'product' => [
                    'id' => $item->getProduct()->getId(),
                    'name' => $item->getProduct()->getName(),
                    'sku' => $item->getProduct()->getSku(),
                ],
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getEffectiveUnitPrice(),
                'originalUnitPrice' => $item->getUnitPrice(),
                'customUnitPrice' => $item->getCustomUnitPrice(),
                'priceModificationReason' => $item->getPriceModificationReason(),
                'total' => $item->getTotal(),
            ];
        }

        return $this->json([
            'id' => $budget->getId(),
            'name' => $budget->getName(),
            'client' => $budget->getClient() ? [
                'id' => $budget->getClient()->getId(),
                'name' => $budget->getClient()->getName(),
                'rif' => $budget->getClient()->getRif(),
            ] : null,
            'items' => $items
        ]);
    }

    // =========================
    // UPDATE
    // =========================
    #[Route('/api/budgets/{id}', methods: ['PUT'])]
    public function update(Request $request, Budget $budget): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $budget->setName($data['name'] ?? $budget->getName());

        // 🔥 CLIENTE
        $client = $this->em->getRepository(Client::class)
            ->find($data['clientId'] ?? null);

        $budget->setClient($client);

        // eliminar items antiguos
        foreach ($budget->getItems() as $item) {
            $this->em->remove($item);
        }

        foreach ($data['items'] ?? [] as $i) {

            $product = $this->productRepo->find($i['productId']);
            if (!$product) continue;

            $lastCost = $this->productCostRepo
                ->findLastCostByProduct($product);

            $originalPrice = $lastCost?->getTotalCost() ?? 0;

            $item = new BudgetItem();
            $item->setProduct($product);
            $item->setQuantity($i['quantity']);

            $item->setUnitPrice($originalPrice);

            $postedPrice = (float)($i['unitPrice'] ?? $originalPrice);
            $item->setCustomUnitPrice($postedPrice);

            $item->setPriceModificationReason(
                $i['priceModificationReason'] ?? null
            );

            $item->calculateTotal();

            $budget->addItem($item);
        }

        $this->em->flush();

        return $this->json(['status' => 'updated']);
    }

    // =========================
    // EXPORT EXCEL
    // =========================
    #[Route('/api/budgets/{id}/export/excel', methods: ['GET'])]
    public function exportExcel(Budget $budget): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // =========================
        // LOGO
        // =========================
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setPath('/var/www/public/DispormedLogo.png'); // ⚠️ ajusta ruta
        $drawing->setHeight(80);
        $drawing->setCoordinates('A1');
        $drawing->setWorksheet($sheet);

        // =========================
        // HEADER
        // =========================
        $sheet->setCellValue('C1', 'GRUPO DISPROMED');
        $sheet->setCellValue('C2', 'J-406973904');

        $sheet->setCellValue('A5', 'CLIENTE:');
        $sheet->setCellValue('B5', $budget->getClient()?->getName());

        $sheet->setCellValue('A6', 'RIF:');
        $sheet->setCellValue('B6', $budget->getClient()?->getRif());

        $sheet->setCellValue('A7', 'DIRECCIÓN:');
        $sheet->setCellValue('B7', $budget->getClient()?->getAddress());

        $sheet->setCellValue('F5', 'FECHA:');
        $sheet->setCellValue('G5', date('Y-m-d'));

        $sheet->mergeCells('A9:G9');
        $sheet->setCellValue('A9', 'COTIZACIÓN');
        $sheet->getStyle('A9')->getFont()->setBold(true)->setSize(14);

        // =========================
        // TABLE
        // =========================
        $startRow = 11;

        $sheet->setCellValue("A$startRow", 'SKU');
        $sheet->setCellValue("B$startRow", 'DESCRIPCIÓN');
        $sheet->setCellValue("E$startRow", 'CANT.');
        $sheet->setCellValue("F$startRow", 'P. UNIT');
        $sheet->setCellValue("G$startRow", 'TOTAL');

        $row = $startRow + 1;

        foreach ($budget->getItems() as $item) {

            $sheet->setCellValue("A$row", $item->getProduct()->getSku());
            $sheet->setCellValue("B$row", $item->getProduct()->getDescription());
            $sheet->mergeCells("B$row:D$row");

            $sheet->setCellValue("E$row", $item->getQuantity());
            $sheet->setCellValue("F$row", $item->getEffectiveUnitPrice());
            $sheet->setCellValue("G$row", $item->getTotal());

            $row++;
        }

        $sheet->setCellValue("F$row", 'TOTAL');
        $sheet->setCellValue("G$row", $budget->getTotal());

        // =========================
        // OUTPUT
        // =========================
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');

        return new Response(
            ob_get_clean(),
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="budget_'.$budget->getId().'.xlsx"'
            ]
        );
    }
    #[Route('/api/logo', methods: ['GET'])]
    public function logo(): Response {
        return $this->file('/path/DispormedLogo.png');
    }
    // =========================
    // EXPORT PDF (🔥 NUEVO)
    // =========================

#[Route('/api/budgets/{id}/export/pdf', methods: ['GET'])]
public function exportPdf(Request $request,LoggerInterface $logger,Budget $budget): Response
{
    $baseUrl = $request->getSchemeAndHttpHost();
    $logoPath = $this->getParameter('kernel.project_dir') . '/public/DispormedLogo.png';

    $logoData = base64_encode(file_get_contents($logoPath));
    
    $logoSrc = 'data:image/png;base64,' . $logoData;
    $logo = $logoSrc;
    $logger->alert("LOGO: ".$logo);
    $html = '
    <style>
        body { font-family: Arial; font-size: 12px; }
        .header { display:flex; justify-content:space-between; align-items:center; }
        .title { text-align:center; font-size:18px; margin-top:20px; font-weight:bold; }
        table { width:100%; border-collapse: collapse; margin-top:20px; }
        th, td { border:1px solid #ccc; padding:6px; }
        th { background:#eee; }
        .right { text-align:right; }
    </style>

    <div class="header">
        <div>
            <img src="'.$logo.'" width="120"/>
        </div>
        <div>
            <strong>GRUPO DISPROMED</strong><br>
            J-406973904
        </div>
    </div>

    <div class="title">COTIZACIÓN</div>

    <p>
        <strong>Cliente:</strong> '.$budget->getClient()?->getName().'<br>
        <strong>RIF:</strong> '.$budget->getClient()?->getRif().'<br>
        <strong>Fecha:</strong> '.date('Y-m-d').'
    </p>

    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Descripción</th>
                <th>Cant</th>
                <th>P.Unit</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($budget->getItems() as $item) {
        $html .= '
            <tr>
                <td>'.$item->getProduct()->getSku().'</td>
                <td>'.$item->getProduct()->getDescription().'</td>
                <td>'.$item->getQuantity().'</td>
                <td>'.$item->getEffectiveUnitPrice().'</td>
                <td>'.$item->getTotal().'</td>
            </tr>';
    }

    $html .= '
        </tbody>
    </table>

    <h3 class="right">TOTAL: '.$budget->getTotal().' €</h3>
    ';

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->set_option('isRemoteEnabled', true);
    $dompdf->render();

    return new Response(
        $dompdf->output(),
        200,
        [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="budget_'.$budget->getId().'.pdf"'
        ]
    );
}

    // =========================
    // DELETE
    // =========================
    #[Route('/api/budgets/{id}', methods: ['DELETE'])]
    public function delete(Budget $budget): JsonResponse
    {
        $this->em->remove($budget);
        $this->em->flush();

        return $this->json(['status' => 'deleted']);
    }
}