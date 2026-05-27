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

class DashboardController extends AbstractController
{
    #[Route('/api/dashboard', methods: ['GET'])]
public function dashboard(EntityManagerInterface $em): JsonResponse
{
    $products = $em->getRepository(Product::class)->findAll();

    $total = count($products);

    $critical = 0;
    $lowStock = 0;
    $noStock = 0;

    foreach ($products as $p) {

        $stock = 0;
        foreach ($p->getBatches() as $b) {
            $stock += $b->getQuantity();
        }

        if ($stock === 0) {
            $noStock++;
        }

        if ($stock > 0 && $stock < $p->getMinStock()) {
            $lowStock++;
        }

        if ($stock <= $p->getMinStock()) {
            $critical++;
        }
    }

    return $this->json([
        'total' => $total,
        'critical' => $critical,
        'lowStock' => $lowStock,
        'noStock' => $noStock
    ]);
}
}