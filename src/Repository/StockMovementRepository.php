<?php

namespace App\Repository;

use App\Entity\Provider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Provider>
 */
class StockMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Provider::class);
    }

    //    /**
    //     * @return Provider[] Returns an array of Provider objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Provider
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function getDailyConsumption(Product $product): array
{
    return $this->createQueryBuilder('m')
        ->select("DATE(m.createdAt) as date, SUM(m.quantity) as total")
        ->where('m.product = :product')
        ->andWhere('m.type = :type')
        ->setParameter('product', $product)
        ->setParameter('type', 'OUT')
        ->groupBy('date')
        ->orderBy('date', 'ASC')
        ->getQuery()
        ->getResult();
}
}
