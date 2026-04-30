<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductCost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductCostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductCost::class);
    }

    public function findLastCost(Product $product): ?ProductCost
    {
        return $this->createQueryBuilder('c')
            ->where('c.product = :product')
            ->setParameter('product', $product)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    public function findLastCostByProductId(int $productId): ?ProductCost
{
    return $this->createQueryBuilder('c')
        ->where('c.product = :productId')
        ->setParameter('productId', $productId)
        ->orderBy('c.createdAt', 'DESC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}
}