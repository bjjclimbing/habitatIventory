<?php

namespace App\Controller;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Entity\Product;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    #[Route('/api/products', methods: ['GET'])]
public function list(Request $request, EntityManagerInterface $em): JsonResponse
{
    $providerId = $request->query->get('provider');
    $search = $request->query->get('name');
    $page = max(1, (int) $request->query->get('page', 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $qb = $em->createQueryBuilder()
        ->select('p', 'b', 'prov')
        ->from(Product::class, 'p')
        ->leftJoin('p.batches', 'b')
        ->leftJoin('p.provider', 'prov');

    // 🔥 filtro provider
    if ($providerId) {
        $qb->andWhere('prov.id = :providerId')
           ->setParameter('providerId', (int)$providerId);
    }

    // 🔥 filtro nombre (clave)
    if ($search) {
        $qb->andWhere('LOWER(p.name) LIKE :search')
           ->setParameter('search', '%' . strtolower($search) . '%');
    }

    // 🔥 total SIN paginar
    $countQb = clone $qb;
    $total = count($countQb->getQuery()->getResult());

    // 🔥 paginación
    $qb->setFirstResult($offset)
       ->setMaxResults($limit);

    $products = $qb->getQuery()->getResult();

    $data = [];

    foreach ($products as $p) {

        $stock = 0;
        foreach ($p->getBatches() as $b) {
            $stock += $b->getQuantity();
        }

        $data[] = [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'stock' => $stock,
            'minStock' => $p->getMinStock(),
            'brand' => $p->getBrand(),
            'provider' => [
                'id' => $p->getProvider()?->getId(),
                'name' => $p->getProvider()?->getName()
            ]
        ];
    }

    return $this->json([
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);
}
    #[Route('/api/products/{id}', methods: ['GET'])]
    public function detail(Product $product): JsonResponse
    {
        $batches = [];

        foreach ($product->getBatches() as $b) {
            $batches[] = [
                'quantity' => $b->getQuantity(),
                'expirationDate' => $b->getExpirationDate()?->format('Y-m-d')
            ];
        }

        return $this->json([
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'provider' => [
                'id' => $product->getProvider()?->getId(),
                'name' => $product->getProvider()?->getName(),
            ],
            'category' => $product->getCategory()?->getName(),
            'batches' => $batches
        ]);
    }

    #[Route('/api/products/{id}/consume', methods: ['POST'])]
    public function consume(
        Product $product,
        Request $request,
        StockService $stockService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $qty = (int) ($data['quantity'] ?? 0);

        if ($qty <= 0) {
            return $this->json(['error' => 'Cantidad inválida'], 400);
        }

        try {
            $stockService->consume($product, $qty);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/products/{id}/movements', methods: ['GET'])]
    public function movements(Product $product): JsonResponse
    {
        $data = [];

        foreach ($product->getMovements() as $m) {
            $data[] = [
                'type' => $m->getType(),
                'quantity' => $m->getQuantity(),
                'date' => $m->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }
}