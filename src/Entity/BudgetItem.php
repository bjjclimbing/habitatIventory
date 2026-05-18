<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "budget_item")]
#[ORM\HasLifecycleCallbacks]
class BudgetItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(
        targetEntity: Budget::class,
        inversedBy: 'items'
    )]
    #[ORM\JoinColumn(nullable: false)]
    private ?Budget $budget = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    private Product $product;

    #[ORM\Column]
    private int $quantity;

    /**
     * Precio original histórico
     */
    #[ORM\Column(type: "float")]
    private float $unitPrice;

    /**
     * Precio realmente usado
     * en el presupuesto
     */
    #[ORM\Column(type: "float", nullable: true)]
    private ?float $customUnitPrice = null;

    /**
     * Motivo opcional
     * del cambio de precio
     */
    #[ORM\Column(
        type: "string",
        length: 255,
        nullable: true
    )]
    private ?string $priceModificationReason = null;

    #[ORM\Column(type: "float")]
    private float $total;

    /**
     * Precio efectivo usado
     * en cálculos/exportaciones
     */
    public function getEffectiveUnitPrice(): float
    {
        return $this->customUnitPrice
            ?? $this->unitPrice;
    }

    public function calculateTotal(): void
    {
        $this->total =
            $this->quantity *
            $this->getEffectiveUnitPrice();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTotal(): void
    {
        $this->calculateTotal();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBudget(): ?Budget
    {
        return $this->budget;
    }

    public function setBudget(
        ?Budget $budget
    ): self
    {
        $this->budget = $budget;

        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(
        Product $product
    ): self
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(
        int $quantity
    ): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Precio original histórico
     */
    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(
        float $unitPrice
    ): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    /**
     * Precio personalizado
     */
    public function getCustomUnitPrice(): ?float
    {
        return $this->customUnitPrice;
    }

    public function setCustomUnitPrice(
        ?float $customUnitPrice
    ): self
    {
        $this->customUnitPrice =
            $customUnitPrice;

        return $this;
    }

    public function getPriceModificationReason(): ?string
    {
        return $this->priceModificationReason;
    }

    public function setPriceModificationReason(
        ?string $priceModificationReason
    ): self
    {
        $this->priceModificationReason =
            $priceModificationReason;

        return $this;
    }

    public function getTotal(): float
    {
        return $this->total;
    }
}