<?php

namespace App\Repository;

use App\Entity\InventoryBatch;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryBatch>
 */
class InventoryBatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryBatch::class);
    }

  

    public function findAvailableBatchesByProductOrderByExpiry(Product $product): array
{
    return $this->createQueryBuilder('b')
        ->where('b.product = :product')
        ->andWhere('b.quantity > 0')
        ->orderBy('b.expirationDate', 'ASC')
        ->setParameter('product', $product)
        ->getQuery()
        ->getResult();
}
}
    //    }


