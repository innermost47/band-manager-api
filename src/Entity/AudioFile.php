<?php

namespace App\Entity;

use App\Repository\AudioFileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AudioFileRepository::class)]
class AudioFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['audioFile'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Song::class, inversedBy: 'audioFiles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Song $song = null;

    #[ORM\Column(length: 255)]
    #[Groups(['audioFile'])]
    private ?string $filename = null;

    #[ORM\Column(length: 255)]
    #[Groups(['audioFile'])]
    private ?string $path = null;

    #[ORM\Column]
    #[Groups(['audioFile'])]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['audioFile'])]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['audioFile'])]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'audiofile')]
    #[Groups(['audioFile'])]
    private ?AudioFileType $audioFileType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSong(): ?Song
    {
        return $this->song;
    }

    public function setSong(?Song $song): static
    {
        $this->song = $song;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAudioFileType(): ?AudioFileType
    {
        return $this->audioFileType;
    }

    public function setAudioFileType(?AudioFileType $audioFileType): static
    {
        $this->audioFileType = $audioFileType;

        return $this;
    }
}
