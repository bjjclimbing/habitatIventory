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
                'name' => $item['product']->getName()
            ] : null,

            'valija' => isset($item['valija']) ? [
                'name' => $item['valija']->getName()
            ] : null,

            'current' => $item['current'] ?? null,
            'min' => $item['min'] ?? null,

            'batch' => isset($item['batch']) ? [
                'expirationDate' => $item['batch']->getExpirationDate()?->format('Y-m-d')
            ] : null
        ];
    }

    // 🔹 caso producto simple
    return [
        'product' => [
            'name' => $item->getName(),
            'stock' => $item->getStock(),
            'min' => $item->getMinStock()
        ]
    ];

}, $data);

return new JsonResponse($clean);
}
}