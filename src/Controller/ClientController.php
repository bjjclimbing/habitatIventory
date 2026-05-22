<?php

namespace App\Controller;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/clients')]
class ClientController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    // =========================
    // LIST
    // =========================
    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $clients = $this->em->getRepository(Client::class)->findAll();

        $data = array_map(fn($c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'rif' => $c->getRif(),
            'address' => $c->getAddress(),
            'phone' => $c->getPhone(),
            'email' => $c->getEmail(),
        ], $clients);

        return $this->json($data);
    }

    // =========================
    // CREATE
    // =========================
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $client = new Client();
        $client->setName($data['name'] ?? '');
        $client->setRif($data['rif'] ?? null);
        $client->setAddress($data['address'] ?? null);
        $client->setPhone($data['phone'] ?? null);
        $client->setEmail($data['email'] ?? null);

        $this->em->persist($client);
        $this->em->flush();

        return $this->json(['id' => $client->getId()]);
    }

    // =========================
    // UPDATE
    // =========================
    #[Route('/{id}', methods: ['PUT'])]
    public function update(Request $request, Client $client): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $client->setName($data['name'] ?? $client->getName());
        $client->setRif($data['rif'] ?? null);
        $client->setAddress($data['address'] ?? null);
        $client->setPhone($data['phone'] ?? null);
        $client->setEmail($data['email'] ?? null);

        $this->em->flush();

        return $this->json(['status' => 'updated']);
    }

    // =========================
    // DELETE
    // =========================
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Client $client): JsonResponse
    {
        $this->em->remove($client);
        $this->em->flush();

        return $this->json(['status' => 'deleted']);
    }
}