<?php

namespace App\Repository;

use App\Entity\Valija;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ValijaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Valija::class);
    }

    /**
     * 🔹 Obtener todas las valijas con definición (productos)
     */
    public function findAllWithProducts(): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.products', 'vp')
            ->addSelect('vp')
            ->leftJoin('vp.product', 'p')
            ->addSelect('p')
            ->getQuery()
            ->getResult();
    }

    /**
     * 🔹 Obtener valija completa (definición + stock + batches)
     */
    public function findOneFull(int $id): ?Valija
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.products', 'vp')->addSelect('vp')
            ->leftJoin('vp.product', 'p')->addSelect('p')
            ->leftJoin('v.stocks', 'vs')->addSelect('vs')
            ->leftJoin('vs.batch', 'b')->addSelect('b')
            ->leftJoin('vs.product', 'sp')->addSelect('sp')
            ->where('v.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 🔹 Obtener todas las valijas completas (para sync masivo)
     */
    public function findAllFull(): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.products', 'vp')->addSelect('vp')
            ->leftJoin('vp.product', 'p')->addSelect('p')
            ->leftJoin('v.stocks', 'vs')->addSelect('vs')
            ->leftJoin('vs.batch', 'b')->addSelect('b')
            ->getQuery()
            ->getResult();
    }

    /**
     * 🔹 Buscar valijas que contienen un producto (clave para eventos)
     */
    public function findByProduct($productId): array
    {
        return $this->createQueryBuilder('v')
            ->innerJoin('v.products', 'vp')
            ->where('vp.product = :product')
            ->setParameter('product', $productId)
            ->getQuery()
            ->getResult();
    }

    /**
     * 🔹 Valijas que necesitan reposición (optimización futura)
     */
    public function findNeedingReplenishment(): array
    {
        // ⚠️ versión básica (puedes optimizar luego con SQL puro)
        return $this->createQueryBuilder('v')
            ->leftJoin('v.products', 'vp')->addSelect('vp')
            ->leftJoin('v.stocks', 'vs')->addSelect('vs')
            ->getQuery()
            ->getResult();
    }
}