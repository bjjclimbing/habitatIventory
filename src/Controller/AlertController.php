<?php

namespace App\Controller;

use App\Service\AlertService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AlertController
{
    public function __construct(
        private AlertService $alertService
    ) {}

    #[Route('/api/alerts', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $grouped = $this->alertService->getAlertsGrouped();

        return new JsonResponse([
            'low_stock' => count($grouped['low_stock'] ?? []),
            'warning' => count($grouped['warning'] ?? []),
            'expired' => count($grouped['expired'] ?? []),
            'valija_low' => count($grouped['valija_low'] ?? []),
            'valija_critical' => count($grouped['valija_critical'] ?? []),
            'valija_expiring' => count($grouped['valija_expiring'] ?? []),
            'valija_expired' => count($grouped['valija_expired'] ?? []),
        ]);
    }
    #[Route('/api/alerts/details', methods: ['GET'])]
    public function details(Request $request): JsonResponse
    {
        $type = $request->query->get('type');

        $grouped = $this->alertService->getAlertsGrouped();

        $data = $grouped[$type] ?? [];

        $clean = array_map(function ($item) {

            // 🔹 caso valijas / batches
            if (is_array($item)) {

                return [
                    'product' => isset($item['product']) ? [
                        'id' => $item['product']->getId(),
                        'sku' => $item['product']->getSku(),
                        'name' => $item['product']->getName()
                    ] : null,

                    'valija' => isset($item['valija']) ? [
                        'id' => $item['valija']->getId(),
                        'name' => $item['valija']->getName()
                    ] : null,

                    'current' => $item['current'] ?? null,
                    'min' => $item['min'] ?? null,

                    'batch' => isset($item['batch']) ? [
                        'expirationDate' => $item['batch']->getExpirationDate()?->format('Y-m-d')
                    ] : null,

                    'days' => $item['days'] ?? null
                ];
            }

            // 🔹 caso producto simple
            return [
                'product' => [
        'id' => $item->getId(),
        'sku' => $item->getSku(),
        'name' => $item->getName(),
        'stock' => $item->getStock(),
        'min' => $item->getMinStock()
    ]
            ];
        }, $data);

        return new JsonResponse($clean);
    }

    #[Route('/api/alerts/summary', methods: ['GET'])]
    public function summary(AlertService $service): JsonResponse
    {
        $data = $service->getAlertsGrouped();

        return new JsonResponse([
            'low_stock' => count($data['low_stock']),
            'warning' => count($data['warning']),
            'expired' => count($data['expired']),
            'valija_low' => count($data['valija_low'] ?? []),
            'valija_critical' => count($data['valija_critical'] ?? []),
            'valija_expiring' => count($data['valija_expiring'] ?? []),
            'valija_expired' => count($data['valija_expired'] ?? []),
        ]);
    }
}
