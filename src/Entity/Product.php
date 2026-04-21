<?php

namespace App\Entity;

use App\Entity\Category;
use App\Entity\Provider;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $sku = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $brand = null;

    #[ORM\Column]
    private ?int $minstock = null;

    #[ORM\ManyToOne(targetEntity: Provider::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Provider $provider = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: InventoryBatch::class, orphanRemoval: true)]
    private Collection $batches;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    public function __construct()
    {
        $this->batches = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    /**
     * Get the value of name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @return  self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the value of brand
     */
    public function getBrand()
    {
        return $this->brand;
    }

    /**
     * Set the value of brand
     *
     * @return  self
     */
    public function setBrand($brand)
    {
        $this->brand = $brand;

        return $this;
    }

    /**
     * Get the value of minstock
     */
    public function getMinstock()
    {
        return $this->minstock;
    }

    /**
     * Set the value of minstock
     *
     * @return  self
     */
    public function setMinstock($minstock)
    {
        $this->minstock = $minstock;

        return $this;
    }

    /**
     * Get the value of provider
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Set the value of provider
     *
     * @return  self
     */
    public function setProvider($provider)
    {
        $this->provider = $provider;

        return $this;
    }

    public function getBatches(): Collection
    {
        return $this->batches;
    }

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
    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        return $this;
    }
}
