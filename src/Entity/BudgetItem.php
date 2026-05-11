<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class BudgetItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Budget::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Budget $budget = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    private Product $product;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column(type: "float")]
    private float $unitPrice;

    #[ORM\Column(type: "float")]
    private float $total;

    public function calculateTotal(): void
    {
        $this->total = $this->quantity * $this->unitPrice;
    }

    // ✅ AUTO CALCULO
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTotal(): void
    {
        $this->calculateTotal();
    }

    public function getId(): ?int { return $this->id; }

    public function getBudget(): ?Budget { return $this->budget; }

    public function setBudget(?Budget $budget): self
    {
        $this->budget = $budget;
        return $this;
    }

    public function getProduct(): Product { return $this->product; }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getQuantity(): int { return $this->quantity; }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitPrice(): float { return $this->unitPrice; }

    public function setUnitPrice(float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getTotal(): float { return $this->total; }
}