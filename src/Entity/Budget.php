<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Budget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private string $name;

    #[ORM\Column(name: "created_at")]
    private \DateTime $createdAt;

    #[ORM\OneToMany(
        mappedBy: 'budget',
        targetEntity: BudgetItem::class,
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $items;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Client $client = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    // ✅ AUTO SET CREATED_AT
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(BudgetItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items[] = $item;
            $item->setBudget($this);
        }

        return $this;
    }

    public function removeItem(BudgetItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getBudget() === $this) {
                $item->setBudget(null);
            }
        }

        return $this;
    }

    public function getTotal(): float
    {
        return array_reduce(
            $this->items->toArray(),
            fn($total, $item) => $total + $item->getTotal(),
            0
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
    public function getClient(): ?Client
    {
        return $this->client;
    }
    public function setClient(?Client $client): self
    {
        $this->client = $client;
        return $this;
    }
}
