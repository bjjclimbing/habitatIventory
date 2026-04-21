<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/api/products', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $products = $em->getRepository(Product::class)->findAll();

        $data = array_map(fn($p) => [
            'id' => $p->getId(),
            'sku' => $p->getSku(),
            'name' => $p->getName(),
            'brand' => $p->getBrand(),
            'minStock' => $p->getMinStock(),
        ], $products);

        return new JsonResponse($data);
    }
}
