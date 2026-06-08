<?php

namespace App\Controller;

use App\Service\InventoryExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;

class InventoryExportController extends AbstractController
{
    #[Route('/api/inventory/export/csv', methods: ['GET'])]
    public function exportCsv(
        InventoryExportService $service
    ): BinaryFileResponse {

        $file = $service->generateCsv();

        return $this->file(
            $file,
            'inventory_' . date('Ymd_His') . '.csv'
        );
    }
}