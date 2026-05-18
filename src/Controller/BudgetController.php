<?php

namespace App\Controller;

use App\Entity\Budget;
use App\Entity\BudgetItem;
use App\Repository\ProductCostRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_ADMIN')]
class BudgetController extends AbstractController
{
    private ProductRepository $productRepo;
    private ProductCostRepository $productCostRepo;
    private EntityManagerInterface $em;

    public function __construct(
        ProductRepository $productRepo,
        ProductCostRepository $productCostRepo,
        EntityManagerInterface $em
    ) {
        $this->productRepo = $productRepo;
        $this->productCostRepo = $productCostRepo;
        $this->em = $em;
    }


// =========================
// CREATE
// =========================
#[Route('/api/budgets', methods: ['POST'])]
public function create(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);

    $budget = new Budget();

    $budget->setName(
        $data['name'] ?? 'Presupuesto'
    );

    foreach ($data['items'] ?? [] as $i) {

        $product = $this->productRepo->find(
            $i['productId']
        );

        if (!$product) {
            continue;
        }

        //
        // PRECIO ORIGINAL
        //
        $lastCost = $this->productCostRepo
            ->findLastCostByProduct($product);

        $originalPrice =
            $lastCost?->getTotalCost() ?? 0;

        //
        // ITEM
        //
        $item = new BudgetItem();

        $item->setProduct($product);

        $item->setQuantity(
            $i['quantity']
        );

        //
        // GUARDAR PRECIO ORIGINAL
        //
        $item->setUnitPrice(
            $originalPrice
        );

        //
        // PRECIO PERSONALIZADO
        // (siempre guardar el enviado desde frontend)
        //
        $postedPrice = (float) (
            $i['unitPrice'] ?? $originalPrice
        );

        $item->setCustomUnitPrice(
            $postedPrice
        );

        //
        // MOTIVO OPCIONAL
        //
        $item->setPriceModificationReason(
            $i['priceModificationReason'] ?? null
        );

        //
        // TOTAL
        //
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
    $from = $request->query->get('from');
    $to = $request->query->get('to');

    $qb = $this->em->getRepository(Budget::class)
        ->createQueryBuilder('b')
        ->orderBy('b.createdAt', 'DESC');

    // 🔍 filtro por nombre
    if ($search) {
        $qb->andWhere('LOWER(b.name) LIKE :search')
           ->setParameter('search', '%' . strtolower($search) . '%');
    }

    // 📅 filtro desde fecha
    if ($from) {
        $qb->andWhere('b.createdAt >= :from')
           ->setParameter('from', new \DateTime($from));
    }

    // 📅 filtro hasta fecha
    if ($to) {
        $qb->andWhere('b.createdAt <= :to')
           ->setParameter('to', new \DateTime($to));
    }

    $budgets = $qb->getQuery()->getResult();

    $data = [];

    foreach ($budgets as $b) {
        $data[] = [
            'id' => $b->getId(),
            'name' => $b->getName(),
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
        'items' => $items
    ]);
}

    // =========================
    // UPDATE
    // =========================
    // =========================
// UPDATE
// =========================
#[Route('/api/budgets/{id}', methods: ['PUT'])]
public function update(
    Request $request,
    Budget $budget
): JsonResponse
{
    $data = json_decode(
        $request->getContent(),
        true
    );

    $budget->setName(
        $data['name'] ?? $budget->getName()
    );

    //
    // LIMPIAR ITEMS ACTUALES
    //
    foreach ($budget->getItems() as $item) {
        $this->em->remove($item);
    }

    //
    // RECREAR ITEMS
    //
    foreach ($data['items'] ?? [] as $i) {

        $product = $this->productRepo->find(
            $i['productId']
        );

        if (!$product) {
            continue;
        }

        //
        // PRECIO ORIGINAL
        //
        $lastCost = $this->productCostRepo
            ->findLastCostByProduct($product);

        $originalPrice =
            $lastCost?->getTotalCost() ?? 0;

        //
        // ITEM
        //
        $item = new BudgetItem();

        $item->setProduct($product);

        $item->setQuantity(
            $i['quantity']
        );

        //
        // GUARDAR PRECIO ORIGINAL
        //
        $item->setUnitPrice(
            $originalPrice
        );

        //
        // PRECIO PERSONALIZADO
        //
        $postedPrice = (float) (
            $i['unitPrice'] ?? $originalPrice
        );

        $item->setCustomUnitPrice(
            $postedPrice
        );

        //
        // MOTIVO
        //
        $item->setPriceModificationReason(
            $i['priceModificationReason'] ?? null
        );

        //
        // TOTAL
        //
        $item->calculateTotal();

        $budget->addItem($item);
    }

    $this->em->flush();

    return $this->json([
        'status' => 'updated'
    ]);
}

    // =========================
    // EXPORT EXCEL
    // =========================
    #[Route('/api/budgets/{id}/export/excel', methods: ['GET'])]
public function exportExcel(Budget $budget): Response
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // =========================
    // HEADER
    // =========================
    $sheet->setCellValue('A1', 'LUGAR Y FECHA DE EMISION:');
    $sheet->setCellValue('C1', date('Y-m-d'));

    $sheet->setCellValue('A3', 'DATOS DEL PROVEEDOR');

    $sheet->setCellValue('A5', 'NOMBRE:');
    $sheet->setCellValue('C5', 'Proveedor Demo'); // 🔥 luego dinámico

    $sheet->setCellValue('A6', 'RIF:');
    $sheet->setCellValue('C6', 'J-XXXXXXX');

    $sheet->setCellValue('A7', 'DIRECCION:');
    $sheet->setCellValue('C7', 'Dirección proveedor');

    $sheet->setCellValue('A8', 'TELEFONO:');
    $sheet->setCellValue('C8', '000000000');

    $sheet->setCellValue('G5', 'ORDEN DE COMPRA');
    $sheet->setCellValue('G6', 'NRO: ' . $budget->getId());

    // =========================
    // TABLE HEADER
    // =========================
    $startRow = 12;

    $sheet->setCellValue("A$startRow", 'REF');
    $sheet->setCellValue("B$startRow", 'DESCRIPCIÓN');
    $sheet->setCellValue("F$startRow", 'CANT.');
    $sheet->setCellValue("G$startRow", 'P. UNITARIO');
    $sheet->setCellValue("H$startRow", 'PRECIO TOTAL');

    $sheet->getStyle("A$startRow:H$startRow")->getFont()->setBold(true);

    // =========================
    // DATA
    // =========================
    $row = $startRow + 1;

    foreach ($budget->getItems() as $item) {

        $sheet->setCellValue("A$row", $item->getProduct()->getSku());
    
        $sheet->setCellValue("B$row", $item->getProduct()->getName());
    
        $sheet->mergeCells("B$row:E$row");
    
        $sheet->setCellValue("F$row", $item->getQuantity());
    
        // ✅ usar precio efectivo (personalizado o normal)
        $sheet->setCellValue("G$row", $item->getEffectiveUnitPrice());
    
        // ✅ total recalculado usando el precio efectivo
        $sheet->setCellValue("H$row", $item->getTotal());
    
        // formato numérico
        $sheet->getStyle("G$row:H$row")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');
    
        $row++;
    }

    // =========================
    // TOTAL FINAL
    // =========================
    $sheet->setCellValue("G$row", 'TOTAL');
    $sheet->setCellValue("H$row", $budget->getTotal());

    $sheet->getStyle("G$row:H$row")->getFont()->setBold(true);

    // =========================
    // ESTILO TABLA (bordes)
    // =========================
    $sheet->getStyle("A$startRow:H$row")->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            ]
        ]
    ]);

    // =========================
    // AUTO SIZE
    // =========================
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // =========================
    // OUTPUT
    // =========================
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

    $response = new Response();
    $response->headers->set(
        'Content-Type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    $response->headers->set(
        'Content-Disposition',
        'attachment; filename="OC_'.$budget->getId().'.xlsx"'
    );

    ob_start();
    $writer->save('php://output');
    $response->setContent(ob_get_clean());

    return $response;
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