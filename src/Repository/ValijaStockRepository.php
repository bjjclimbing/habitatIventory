<?php

namespace App\Repository;

use App\Entity\Provider;
use App\Entity\ValijaStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Provider>
 */
class ValijaStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ValijaStock::class);
    }

    public function getTotalByValijaAndProduct($valija, $product): int
    {
        return (int) $this->createQueryBuilder('vs')
            ->select('COALESCE(SUM(vs.quantity), 0)')
            ->where('vs.valija = :valija')
            ->andWhere('vs.product = :product')
            ->setParameter('valija', $valija)
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }
    
}
