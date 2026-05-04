<?php

namespace App\Controller;

use App\Entity\ValijaProduct;
use App\Repository\ProductRepository;
use App\Repository\ValijaProductRepository;
use App\Repository\ValijaRepository;
use App\Service\AlertService;
use App\Service\ValijaSyncService;
use Doctrine\ORM\EntityManagerInterface;
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
    #[Route('/api/valijas/{id}', methods: ['GET'])]
    public function getValija(int $id): JsonResponse
    {
        $valija = $this->valijaRepo->find($id);

        if (!$valija) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $products = [];

        foreach ($valija->getProducts() as $vp) {
            $products[] = [
                'id' => $vp->getId(),
                'stockMin' => $vp->getStockMin(),
                'product' => [
                    'id' => $vp->getProduct()->getId(),
                    'name' => $vp->getProduct()->getName()
                ]
            ];
        }

        return new JsonResponse([
            'id' => $valija->getId(),
            'name' => $valija->getName(),
            'products' => $products
        ]);
    }

    #[Route('/api/valijas/{id}/products', methods: ['POST'])]
    public function addProductToValija(
        int $id,
        Request $request,
        ValijaRepository $valijaRepo,
        ProductRepository $productRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        $valija = $valijaRepo->find($id);

        if (!$valija) {
            return new JsonResponse(['error' => 'Valija not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['productId'])) {
            return new JsonResponse(['error' => 'productId required'], 400);
        }

        $product = $productRepo->find($data['productId']);

        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }

        $stockMin = (int) ($data['stockMin'] ?? 10);

        // evitar duplicados
        foreach ($valija->getProducts() as $vp) {
            if ($vp->getProduct()->getId() === $product->getId()) {
                return new JsonResponse(['error' => 'Product already in valija'], 400);
            }
        }

        $vp = new ValijaProduct();
        $vp->setValija($valija);
        $vp->setProduct($product);
        $vp->setStockMin($stockMin);

        $em->persist($vp);
        $em->flush();

        return new JsonResponse(['status' => 'ok']);
    }
    #[Route('/api/valijas/products/{id}', methods: ['PUT'])]
    public function updateValijaProduct(
        int $id,
        Request $request,
        ValijaProductRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {

        $vp = $repo->find($id);

        if (!$vp) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['stockMin'])) {
            return new JsonResponse(['error' => 'stockMin required'], 400);
        }

        $vp->setStockMin((int) $data['stockMin']);

        $em->flush();

        return new JsonResponse(['status' => 'ok']);
    }
    #[Route('/api/valijas/products/{id}', methods: ['DELETE'])]
    public function deleteValijaProduct(
        int $id,
        ValijaProductRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {

        $vp = $repo->find($id);

        if (!$vp) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $em->remove($vp);
        $em->flush();

        return new JsonResponse(['status' => 'deleted']);
    }
    #[Route('/api/valijas/{id}/sync', methods: ['POST'])]
    public function syncValija(
        int $id,
        ValijaRepository $valijaRepo,
        ValijaSyncService $syncService
    ): JsonResponse {

        $valija = $valijaRepo->find($id);

        if (!$valija) {
            return new JsonResponse(['error' => 'Valija not found'], 404);
        }

        try {
            $syncService->sync($valija);

            return new JsonResponse([
                'status' => 'ok',
                'message' => 'Valija sincronizada'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    #[Route('/api/valijas', methods: ['GET'])]
    public function list(ValijaRepository $repo): JsonResponse
    {
        $valijas = $repo->findAll();

        $data = array_map(function ($v) {
            return [
                'id' => $v->getId(),
                'name' => $v->getName(),
            ];
        }, $valijas);

        return new JsonResponse($data);
    }
}
