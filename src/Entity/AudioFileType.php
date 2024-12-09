<?php

namespace App\Entity;

use App\Repository\AudioFileTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AudioFileTypeRepository::class)]
class AudioFileType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['audioFileType'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['audioFileType', 'audioFile'])]
    private ?string $name = null;

    /**
     * @var Collection<int, AudioFile>
     */
    #[ORM\OneToMany(targetEntity: AudioFile::class, mappedBy: 'audioFileType')]
    private Collection $audiofile;

    public function __construct()
    {
        $this->audiofile = new ArrayCollection();
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

    /**
     * @return Collection<int, AudioFile>
     */
    public function getAudiofile(): Collection
    {
        return $this->audiofile;
    }

    public function addAudiofile(AudioFile $audiofile): static
    {
        if (!$this->audiofile->contains($audiofile)) {
            $this->audiofile->add($audiofile);
            $audiofile->setAudioFileType($this);
        }

        return $this;
    }

    public function removeAudiofile(AudioFile $audiofile): static
    {
        if ($this->audiofile->removeElement($audiofile)) {
            // set the owning side to null (unless already changed)
            if ($audiofile->getAudioFileType() === $this) {
                $audiofile->setAudioFileType(null);
            }
        }

        return $this;
    }
}
