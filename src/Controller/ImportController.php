<?php

namespace App\Controller;

use App\Service\PurchaseCsvImporter;
use App\Service\SalesCsvImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ImportController extends AbstractController
{
    #[Route('/api/import/purchases', methods: ['POST'])]
    public function importPurchases(
        Request $request,
        PurchaseCsvImporter $importer
    ): JsonResponse {

        $file = $request->files->get('file');
        $mode = $request->request->get('mode', PurchaseCsvImporter::MODE_CREATE);

        if (!$file) {
            return new JsonResponse(['error' => 'File is required'], 400);
        }

        try {
            $importer->import($file->getPathname(), $mode);

            return new JsonResponse([
                'status' => 'ok',
                'message' => 'Purchases imported'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    #[Route('/api/import/sales', methods: ['POST'])]
    public function importSales(
        Request $request,
        SalesCsvImporter $importer
    ): JsonResponse {

        $file = $request->files->get('file');

        if (!$file) {
            return new JsonResponse(['error' => 'File is required'], 400);
        }

        try {
            $importer->import($file->getPathname());

            return new JsonResponse([
                'status' => 'ok',
                'message' => 'Sales imported'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
