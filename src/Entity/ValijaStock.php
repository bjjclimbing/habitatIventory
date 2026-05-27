<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ValijaStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Valija::class, inversedBy: 'stocks')]
    #[ORM\JoinColumn(nullable: false)]
    private Valija $valija;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: InventoryBatch::class)]
    #[ORM\JoinColumn(nullable: false)]
    private InventoryBatch $batch;

    #[ORM\Column]
    private int $quantity;

    public function increase(int $qty): void
    {
        $this->quantity += $qty;
    }

    public function decrease(int $qty): void
    {
        $this->quantity -= $qty;
    }

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
     * Get the value of batch
     */ 
    public function getBatch()
    {
        return $this->batch;
    }

    /**
     * Set the value of batch
     *
     * @return  self
     */ 
    public function setBatch($batch)
    {
        $this->batch = $batch;

        return $this;
    }

    /**
     * Get the value of quantity
     */ 
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Set the value of quantity
     *
     * @return  self
     */ 
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }
}