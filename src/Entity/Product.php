<?php

namespace App\Entity;

use App\Entity\Category;
use App\Entity\Provider;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $sku = null;

    #[Groups(['product:read'])]
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $brand = null;

    #[Groups(['product:read'])]
    #[ORM\Column]
    private ?int $minstock = null;

    #[Groups(['product:read'])]
    #[ORM\ManyToOne(targetEntity: Provider::class)]
    private ?Provider $provider = null;

    #[Groups(['product:read'])]
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: InventoryBatch::class, orphanRemoval: true)]
    private Collection $batches;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: StockMovement::class)]
    private Collection $movements;

    public function __construct()
    {
        $this->batches = new ArrayCollection();
        $this->movements = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getSku(): ?string { return $this->sku; }
    public function setSku(string $sku): static { $this->sku = $sku; return $this; }

    public function getName() { return $this->name; }
    public function setName($name) { $this->name = $name; return $this; }

    public function getBrand() { return $this->brand; }
    public function setBrand($brand) { $this->brand = $brand; return $this; }

    #[Groups(['product:read'])]
    public function getMinStock(): ?int { return $this->minstock; }
    public function setMinstock($minstock) { $this->minstock = $minstock; return $this; }

    public function getProvider() { return $this->provider; }
    public function setProvider($provider) { $this->provider = $provider; return $this; }

    public function getBatches(): Collection { return $this->batches; }

    public function addBatch(InventoryBatch $batch): self
    {
        if (!$this->batches->contains($batch)) {
            $this->batches[] = $batch;
            $batch->setProduct($this);
        }
        return $this;
    }

    public function removeBatch(InventoryBatch $batch): self
    {
        if ($this->batches->removeElement($batch)) {
            if ($batch->getProduct() === $this) {
                $batch->setProduct(null);
            }
        }
        return $this;
    }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): self { $this->category = $category; return $this; }

    #[Groups(['product:read'])]
    public function getStock(): int
    {
        return array_sum(
            array_map(fn($b) => $b->getQuantity(), $this->batches->toArray())
        );
    }

    public function getMovements(): Collection { return $this->movements; }
}