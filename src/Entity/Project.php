<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['project', 'user', 'event:read', 'administrative_task'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['project', 'user', 'event:read', 'administrative_task'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['project', 'event:read'])]
    private ?string $description = null;

    /**
     * @var Collection<int, Song>
     */
    #[ORM\OneToMany(targetEntity: Song::class, mappedBy: 'project')]
    #[Groups(['project', 'event:read'])]
    #[MaxDepth(1)]
    private Collection $songs;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['project', 'event:read'])]
    private ?string $profileImage = null;

    /**
     * @var Collection<int, Gallery>
     */
    #[ORM\OneToMany(targetEntity: Gallery::class, mappedBy: 'project')]
    private Collection $galleries;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'projects')]
    #[Groups(['project', 'event:read'])]
    #[MaxDepth(1)]
    private Collection $members;

    /**
     * @var Collection<int, Invitation>
     */
    #[ORM\OneToMany(targetEntity: Invitation::class, mappedBy: 'project')]
    private Collection $invitations;

    /**
     * @var Collection<int, AdministrativeTask>
     */
    #[ORM\OneToMany(targetEntity: AdministrativeTask::class, mappedBy: 'project')]
    private Collection $administrativeTasks;

    #[ORM\Column(nullable: true)]
    #[Groups(['project', 'user', 'event:read'])]
    private ?bool $isPublic = null;

    public function __construct()
    {
        $this->songs = new ArrayCollection();
        $this->galleries = new ArrayCollection();
        $this->members = new ArrayCollection();
        $this->invitations = new ArrayCollection();
        $this->administrativeTasks = new ArrayCollection();
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

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Song>
     */
    public function getSongs(): Collection
    {
        return $this->songs;
    }

    public function addSong(Song $song): static
    {
        if (!$this->songs->contains($song)) {
            $this->songs->add($song);
            $song->setProject($this);
        }

        return $this;
    }

    public function removeSong(Song $song): static
    {
        if ($this->songs->removeElement($song)) {
            // set the owning side to null (unless already changed)
            if ($song->getProject() === $this) {
                $song->setProject(null);
            }
        }

        return $this;
    }

    public function getProfileImage(): ?string
    {
        return $this->profileImage;
    }

    public function setProfileImage(?string $profileImage): static
    {
        $this->profileImage = $profileImage;

        return $this;
    }

    /**
     * @return Collection<int, Gallery>
     */
    public function getGalleries(): Collection
    {
        return $this->galleries;
    }

    public function addGallery(Gallery $gallery): static
    {
        if (!$this->galleries->contains($gallery)) {
            $this->galleries->add($gallery);
            $gallery->setProject($this);
        }

        return $this;
    }

    public function removeGallery(Gallery $gallery): static
    {
        if ($this->galleries->removeElement($gallery)) {
            // set the owning side to null (unless already changed)
            if ($gallery->getProject() === $this) {
                $gallery->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->addProject($this);
        }

        return $this;
    }

    public function removeMember(User $member): static
    {
        if ($this->members->removeElement($member)) {
            $member->removeProject($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(Invitation $invitation): static
    {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations->add($invitation);
            $invitation->setProject($this);
        }

        return $this;
    }

    public function removeInvitation(Invitation $invitation): static
    {
        if ($this->invitations->removeElement($invitation)) {
            // set the owning side to null (unless already changed)
            if ($invitation->getProject() === $this) {
                $invitation->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AdministrativeTask>
     */
    public function getAdministrativeTasks(): Collection
    {
        return $this->administrativeTasks;
    }

    public function addAdministrativeTask(AdministrativeTask $administrativeTask): static
    {
        if (!$this->administrativeTasks->contains($administrativeTask)) {
            $this->administrativeTasks->add($administrativeTask);
            $administrativeTask->setProject($this);
        }

        return $this;
    }

    public function removeAdministrativeTask(AdministrativeTask $administrativeTask): static
    {
        if ($this->administrativeTasks->removeElement($administrativeTask)) {
            // set the owning side to null (unless already changed)
            if ($administrativeTask->getProject() === $this) {
                $administrativeTask->setProject(null);
            }
        }

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
