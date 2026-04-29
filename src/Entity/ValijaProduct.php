<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: "valija_product_unique", columns: ["valija_id", "product_id"])]
class ValijaProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Valija::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private Valija $valija;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column]
    private int $stockMin;

    // getters / setters...

    /**
     * Get the value of id
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return  self
     */ 
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of valija
     */ 
    public function getValija()
    {
        return $this->valija;
    }

    /**
     * Set the value of valija
     *
     * @return  self
     */ 
    public function setValija($valija)
    {
        $this->valija = $valija;

        return $this;
    }

    /**
     * Get the value of product
     */ 
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set the value of product
     *
     * @return  self
     */ 
    public function setProduct($product)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get the value of stockMin
     */ 
    public function getStockMin()
    {
        return $this->stockMin;
    }

    /**
     * Set the value of stockMin
     *
     * @return  self
     */ 
    public function setStockMin($stockMin)
    {
        $this->stockMin = $stockMin;

        return $this;
    }
}