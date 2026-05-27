<?php

namespace App\Repository;

use App\Entity\Valija;
use App\Entity\Product;
use App\Entity\ValijaProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ValijaProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ValijaProduct::class);
    }

    /**
     * 🔹 Obtener todos los productos de una valija (definición)
     */
    public function findByValija(Valija $valija): array
    {
        return $this->createQueryBuilder('vp')
            ->leftJoin('vp.product', 'p')
            ->addSelect('p')
            ->where('vp.valija = :valija')
            ->setParameter('valija', $valija)
            ->getQuery()
            ->getResult();
    }

    /**
     * 🔹 Obtener definición de un producto dentro de una valija
     */
    public function findOneByValijaAndProduct(Valija $valija, Product $product): ?ValijaProduct
    {
        return $this->createQueryBuilder('vp')
            ->where('vp.valija = :valija')
            ->andWhere('vp.product = :product')
            ->setParameter('valija', $valija)
            ->setParameter('product', $product)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 🔹 Obtener todas las valijas donde está un producto
     * 👉 clave para eventos (ventas)
     */
    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('vp')
            ->leftJoin('vp.valija', 'v')
            ->addSelect('v')
            ->where('vp.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult();
    }

    /**
     * 🔹 Obtener solo los stockMin por valija (optimizado)
     */
    public function getStockMinMapByValija(Valija $valija): array
    {
        $rows = $this->createQueryBuilder('vp')
            ->select('IDENTITY(vp.product) as product_id, vp.stockMin')
            ->where('vp.valija = :valija')
            ->setParameter('valija', $valija)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['product_id']] = $row['stockMin'];
        }

        return $map;
    }

    /**
     * 🔹 Obtener productos que necesitan reposición (solo definición)
     * ⚠️ útil si luego haces lógica SQL más avanzada
     */
    public function findWithStockMinGreaterThan(int $min): array
    {
        return $this->createQueryBuilder('vp')
            ->where('vp.stockMin > :min')
            ->setParameter('min', $min)
            ->getQuery()
            ->getResult();
    }
}