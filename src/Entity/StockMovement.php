<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class StockMovement
{
    const TYPE_OUT='OUT';
    CONST TYPE_IN='IN';
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // 🔹 Producto
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    // 🔹 Batch (opcional pero MUY útil)
    #[ORM\ManyToOne(targetEntity: InventoryBatch::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InventoryBatch $batch = null;

    // 🔹 Tipo: IN / OUT
    #[ORM\Column(length: 10)]
    private string $type;

    // 🔹 Cantidad
    #[ORM\Column]
    private int $quantity;

    // 🔹 Fecha
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ======================
    // GETTERS / SETTERS
    // ======================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getBatch(): ?InventoryBatch
    {
        return $this->batch;
    }

    public function setBatch(?InventoryBatch $batch): self
    {
        $this->batch = $batch;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}