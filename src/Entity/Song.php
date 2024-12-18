<?php

namespace App\Entity;

use App\Repository\SongRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Entity\Lyrics;

#[ORM\Entity(repositoryClass: SongRepository::class)]
class Song
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['song'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['song'])]
    private ?string $title = null;

    #[ORM\Column]
    #[Groups(['song'])]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['song'])]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\OneToMany(mappedBy: 'song', targetEntity: AudioFile::class, cascade: ['persist', 'remove'])]
    #[Groups(['song'])]
    private Collection $audioFiles;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'songs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['song'])]
    private ?string $bpm = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['song'])]
    private ?string $scale = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['song'])]
    private ?bool $isPublic = null;

    #[ORM\OneToMany(mappedBy: 'song', targetEntity: Lyrics::class, cascade: ['persist', 'remove'])]
    private Collection $lyrics;

    #[ORM\OneToMany(mappedBy: 'song', targetEntity: Tablature::class, cascade: ['persist', 'remove'])]
    private Collection $tablatures;

    public function __construct()
    {
        $this->audioFiles = new ArrayCollection();
        $this->lyrics = new ArrayCollection();
        $this->tablatures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getAudioFiles(): Collection
    {
        return $this->audioFiles;
    }

    public function addAudioFile(AudioFile $audioFile): static
    {
        if (!$this->audioFiles->contains($audioFile)) {
            $this->audioFiles->add($audioFile);
            $audioFile->setSong($this);
        }

        return $this;
    }

    public function removeAudioFile(AudioFile $audioFile): static
    {
        if ($this->audioFiles->removeElement($audioFile)) {
            if ($audioFile->getSong() === $this) {
                $audioFile->setSong(null);
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

    public function getBpm(): ?string
    {
        return $this->bpm;
    }

    public function setBpm(string $bpm): static
    {
        $this->bpm = $bpm;

        return $this;
    }

    public function getScale(): ?string
    {
        return $this->scale;
    }

    public function setScale(?string $scale): static
    {
        $this->scale = $scale;

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

    public function getLyrics(): Collection
    {
        return $this->lyrics;
    }

    public function addLyrics(Lyrics $lyrics): static
    {
        if (!$this->lyrics->contains($lyrics)) {
            $this->lyrics->add($lyrics);
            $lyrics->setSong($this);
        }
        return $this;
    }

    public function removeLyrics(Lyrics $lyrics): static
    {
        if ($this->lyrics->removeElement($lyrics)) {
            if ($lyrics->getSong() === $this) {
                $lyrics->setSong(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Tablature>
     */
    public function getTablatures(): Collection
    {
        return $this->tablatures;
    }

    public function addTablature(Tablature $tablature): static
    {
        if (!$this->tablatures->contains($tablature)) {
            $this->tablatures->add($tablature);
            $tablature->setSong($this);
        }

        return $this;
    }

    public function removeTablature(Tablature $tablature): static
    {
        if ($this->tablatures->removeElement($tablature)) {
            // Set the owning side to null (unless already changed)
            if ($tablature->getSong() === $this) {
                $tablature->setSong(null);
            }
        }

        return $this;
    }
}
