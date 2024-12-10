<?php

namespace App\Entity;

use App\Entity\Event;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class EventException
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'exceptions')]
    private ?Event $parent_event = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $exception_date = null;

    #[ORM\Column]
    private bool $is_cancelled = false;

    #[ORM\Column]
    private ?string $reason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $rescheduled_start = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $rescheduled_end = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alternate_location = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentEvent(): ?Event
    {
        return $this->parent_event;
    }

    public function setParentEvent(?Event $parent_event): static
    {
        $this->parent_event = $parent_event;
        return $this;
    }

    public function getExceptionDate(): ?\DateTimeImmutable
    {
        return $this->exception_date;
    }

    public function setExceptionDate(\DateTimeImmutable $exception_date): static
    {
        $this->exception_date = $exception_date;
        return $this;
    }

    public function isIsCancelled(): bool
    {
        return $this->is_cancelled;
    }

    public function setIsCancelled(bool $is_cancelled): static
    {
        $this->is_cancelled = $is_cancelled;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getRescheduledStart(): ?\DateTimeImmutable
    {
        return $this->rescheduled_start;
    }

    public function setRescheduledStart(?\DateTimeImmutable $rescheduled_start): static
    {
        $this->rescheduled_start = $rescheduled_start;
        return $this;
    }

    public function getRescheduledEnd(): ?\DateTimeImmutable
    {
        return $this->rescheduled_end;
    }

    public function setRescheduledEnd(?\DateTimeImmutable $rescheduled_end): static
    {
        $this->rescheduled_end = $rescheduled_end;
        return $this;
    }

    public function getAlternateLocation(): ?string
    {
        return $this->alternate_location;
    }

    public function setAlternateLocation(?string $alternate_location): static
    {
        $this->alternate_location = $alternate_location;
        return $this;
    }

    public function isRescheduled(): bool
    {
        return $this->rescheduled_start !== null && $this->rescheduled_end !== null;
    }
}
