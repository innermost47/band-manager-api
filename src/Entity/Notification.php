<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['notification:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'notifications')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Groups(['notification:read'])]
    private ?string $content = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['notification:read'])]
    private ?bool $hasSeen = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['notification:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    #[Groups(['notification:read'])]
    private ?string $frontEndUrl = null;

    #[ORM\Column(length: 255)]
    #[Groups(['notification:read'])]
    private ?string $type = null;

    #[ORM\ManyToOne(inversedBy: 'notifications')]
    private ?Project $project = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['notification:read'])]
    private ?array $metadata = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function hasSeen(): ?bool
    {
        return $this->hasSeen;
    }

    public function setHasSeen(bool $hasSeen): static
    {
        $this->hasSeen = $hasSeen;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getFrontEndUrl(): ?string
    {
        return $this->frontEndUrl;
    }

    public function setFrontEndUrl(string $frontEndUrl): static
    {
        $this->frontEndUrl = $frontEndUrl;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }
}
