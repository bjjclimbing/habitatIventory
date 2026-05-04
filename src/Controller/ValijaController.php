<?php
namespace App\Controller;

use App\Repository\ValijaRepository;
use App\Service\AlertService;
use App\Service\ValijaSyncService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ValijaController
{
    public function __construct(
        private AlertService $alertService,
        private ValijaRepository $valijaRepo,
        private ValijaSyncService $valijaSyncService
    ) {}

    #[Route('/api/valijas/sync', methods: ['POST'])]
    public function sync(): JsonResponse
    {
        $valijas = $this->valijaRepo->findAll();
    
        foreach ($valijas as $valija) {
            $this->valijaSyncService->sync($valija);
        }
    
        return new JsonResponse([
            'status' => 'ok',
            'message' => 'Valijas sincronizadas'
        ]);
    }
}