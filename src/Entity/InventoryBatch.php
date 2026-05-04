<?php

namespace App\Entity;

use App\Repository\InventoryBatchRepository;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: InventoryBatchRepository::class)]
#[ORM\Table(name: 'inventory_batch')]
class InventoryBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?int $id = null;

    // 🔹 Relación con producto
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    // 🔹 Cantidad disponible en este lote
    #[ORM\Column]
    #[Groups(['product:read'])]
    private int $quantity;

    // 🔹 Fecha de caducidad (puede ser null)
    #[Groups(['product:read'])]
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expirationDate = null;

    // 🔹 Fecha de creación del lote
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $commissionPercent = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ======================
    // GETTERS & SETTERS
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

    public function getQuantity(): int
    {
        return $this->quantity ?? 0;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTimeInterface $expirationDate): self
    {
        $this->expirationDate = $expirationDate;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function decrease(int $qty): void
    {
        if ($qty < 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        if ($this->quantity < $qty) {
            throw new \RuntimeException(sprintf(
                'Not enough stock in batch %d. Available: %d, requested: %d',
                $this->id,
                $this->quantity,
                $qty
            ));
        }

        $this->quantity -= $qty;
    }
    public function increase(int $qty): void
    {
        if ($qty < 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        $this->quantity += $qty;
    }
    public function getCommissionPercent(): ?float
    {
        return $this->commissionPercent;
    }

    public function setCommissionPercent(?float $commissionPercent): self
    {
        $this->commissionPercent = $commissionPercent;
        return $this;
    }
}
