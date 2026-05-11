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
        $budget->setName($data['name'] ?? 'Presupuesto');

        foreach ($data['items'] ?? [] as $i) {

            $product = $this->productRepo->find($i['productId']);
            if (!$product) continue;

            $lastCost = $this->productCostRepo->findLastCostByProduct($product);
            $price = $lastCost?->getTotalCost() ?? 0;

            $item = new BudgetItem();
            $item->setProduct($product);
            $item->setQuantity($i['quantity']);
            $item->setUnitPrice($price);
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
    public function list(): JsonResponse
    {
        $budgets = $this->em->getRepository(Budget::class)->findAll();

        $data = [];

        foreach ($budgets as $b) {

            $items = [];

            foreach ($b->getItems() as $item) {
                $items[] = [
                    'id' => $item->getId(),
                    'product' => [
                        'id' => $item->getProduct()->getId(),
                        'name' => $item->getProduct()->getName(),
                    ],
                    'quantity' => $item->getQuantity(),
                    'unitPrice' => $item->getUnitPrice(),
                    'total' => $item->getTotal(),
                ];
            }

            $data[] = [
                'id' => $b->getId(),
                'name' => $b->getName(),
                'items' => $items,
                'total' => array_sum(array_map(fn($i) => $i['total'], $items))
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
            'unitPrice' => $item->getUnitPrice(), // 🔥 CLAVE
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
    #[Route('/api/budgets/{id}', methods: ['PUT'])]
    public function update(Request $request, Budget $budget): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $budget->setName($data['name'] ?? $budget->getName());

        // 🔥 limpiar items actuales
        foreach ($budget->getItems() as $item) {
            $this->em->remove($item);
        }

        // 🔥 recrear items
        foreach ($data['items'] ?? [] as $i) {

            $product = $this->productRepo->find($i['productId']);
            if (!$product) continue;

            $lastCost = $this->productCostRepo->findLastCostByProduct($product);
            $price = $lastCost?->getTotalCost() ?? 0;

            $item = new BudgetItem();
            $item->setProduct($product);
            $item->setQuantity($i['quantity']);
            $item->setUnitPrice($price);

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
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Producto');
        $sheet->setCellValue('B1', 'Cantidad');
        $sheet->setCellValue('C1', 'Precio');
        $sheet->setCellValue('D1', 'Total');

        $row = 2;

        foreach ($budget->getItems() as $item) {
            $sheet->setCellValue("A$row", $item->getProduct()->getName());
            $sheet->setCellValue("B$row", $item->getQuantity());
            $sheet->setCellValue("C$row", $item->getUnitPrice());
            $sheet->setCellValue("D$row", $item->getTotal());
            $row++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="budget_'.$budget->getId().'.xlsx"');

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