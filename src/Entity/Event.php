<?php

namespace App\Entity;

use App\Repository\EventRepository;
use App\Entity\EventException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['event:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['event:read'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['event:read'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['event:read'])]
    private ?\DateTimeImmutable $start_date = null;

    #[ORM\Column]
    #[Groups(['event:read'])]
    private ?\DateTimeImmutable $end_date = null;

    #[ORM\Column(length: 255)]
    #[Groups(['event:read'])]
    private ?string $location = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['event:read'])]
    private ?string $recurrence_type = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['event:read'])]
    private ?int $recurrence_interval = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['event:read'])]
    private ?array $recurrence_days = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['event:read'])]
    private ?array $recurrence_months = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['event:read'])]
    private ?\DateTimeImmutable $recurrence_end = null;

    #[ORM\OneToMany(mappedBy: 'parent_event', targetEntity: EventException::class)]
    #[Groups(['event:read'])]
    private Collection $exceptions;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'events')]
    private ?Project $project = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['event:read'])]
    #[SerializedName('is_public')]
    private ?bool $isPublic = null;

    public function __construct()
    {
        $this->exceptions = new ArrayCollection();
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

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->start_date;
    }

    public function setStartDate(\DateTimeImmutable $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->end_date;
    }

    public function setEndDate(\DateTimeImmutable $end_date): static
    {
        $this->end_date = $end_date;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getRecurrenceType(): ?string
    {
        return $this->recurrence_type;
    }

    public function setRecurrenceType(?string $recurrence_type): static
    {
        $this->recurrence_type = $recurrence_type;
        return $this;
    }

    public function getRecurrenceInterval(): ?int
    {
        return $this->recurrence_interval;
    }

    public function setRecurrenceInterval(?int $recurrence_interval): static
    {
        $this->recurrence_interval = $recurrence_interval;
        return $this;
    }

    public function getRecurrenceDays(): ?array
    {
        return $this->recurrence_days;
    }

    public function setRecurrenceDays(?array $recurrence_days): static
    {
        $this->recurrence_days = $recurrence_days;
        return $this;
    }

    public function getRecurrenceMonths(): ?array
    {
        return $this->recurrence_months;
    }

    public function setRecurrenceMonths(?array $recurrence_months): static
    {
        $this->recurrence_months = $recurrence_months;
        return $this;
    }

    public function getRecurrenceEnd(): ?\DateTimeImmutable
    {
        return $this->recurrence_end;
    }

    public function setRecurrenceEnd(?\DateTimeImmutable $recurrence_end): static
    {
        $this->recurrence_end = $recurrence_end;
        return $this;
    }

    /**
     * @return Collection<int, EventException>
     */
    public function getExceptions(): Collection
    {
        return $this->exceptions;
    }

    public function addException(EventException $exception): static
    {
        if (!$this->exceptions->contains($exception)) {
            $this->exceptions->add($exception);
            $exception->setParentEvent($this);
        }
        return $this;
    }

    public function removeException(EventException $exception): static
    {
        if ($this->exceptions->removeElement($exception)) {
            if ($exception->getParentEvent() === $this) {
                $exception->setParentEvent(null);
            }
        }
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

    public function isPublic(): ?bool
    {
        return $this->isPublic;
    }

    public function setPublic(?bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }
}
