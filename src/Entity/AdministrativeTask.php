<?php

namespace App\Entity;

use App\Repository\AdministrativeTaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AdministrativeTaskRepository::class)]
class AdministrativeTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['administrative_task'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['administrative_task'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['administrative_task'])]
    private ?string $description = null;
    
    #[ORM\Column(type: 'json')]
    #[Groups(['administrative_task'])]
    private array $tableStructure = []; 
    
    #[ORM\Column(type: 'json')]
    #[Groups(['administrative_task'])]
    private array $tableValues = []; 

    #[ORM\Column]
    #[Groups(['administrative_task'])]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['administrative_task'])]
    private ?\DateTimeImmutable $completed_at = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'administrativeTasks')]
    private Collection $assigned_to;

    #[ORM\ManyToOne(inversedBy: 'administrativeTasks')]
    #[Groups(['administrative_task'])]
    private ?Project $project = null;

    public function __construct()
    {
        $this->assigned_to = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completed_at;
    }

    public function setCompletedAt(?\DateTimeImmutable $completed_at): static
    {
        $this->completed_at = $completed_at;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAssignedTo(): Collection
    {
        return $this->assigned_to;
    }

    public function addAssignedTo(User $assignedTo): static
    {
        if (!$this->assigned_to->contains($assignedTo)) {
            $this->assigned_to->add($assignedTo);
        }

        return $this;
    }

    public function removeAssignedTo(User $assignedTo): static
    {
        $this->assigned_to->removeElement($assignedTo);

        return $this;
    }
    
    public function getTableStructure(): array
    {
        return $this->tableStructure;
    }
    
    public function setTableStructure(array $tableStructure): self
    {
        $this->tableStructure = $tableStructure;
    
        return $this;
    }
    
    public function getTableValues(): array
    {
        return $this->tableValues;
    }
    
    public function setTableValues(array $tableValues): self
    {
        $this->tableValues = $tableValues;
    
        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }
}
