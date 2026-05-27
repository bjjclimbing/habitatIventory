<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Provider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProviderController extends AbstractController
{
    

    #[Route('/api/providers', methods: ['GET'])]
    public function providerList(EntityManagerInterface $em): JsonResponse
    {
        $providers = $em->getRepository(Provider::class)->findAll();

        $data = array_map(fn($p) => [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'links' => [
                'self' => "/api/providers/" . $p->getId()
            ]
        ], $providers);

        return new JsonResponse($data);
    }
}
