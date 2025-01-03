<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user', 'project'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['user', 'project', 'message:read'])]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    #[Groups(['user', 'project'])]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column]
    #[Groups(['user'])]
    private array $roles = [];

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user'])]
    private ?string $sacemNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user'])]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user'])]
    private ?string $phone = null;

    /**
     * @var Collection<int, AdministrativeTask>
     */
    #[ORM\ManyToMany(targetEntity: AdministrativeTask::class, mappedBy: 'assigned_to')]
    private Collection $administrativeTasks;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\ManyToMany(targetEntity: Task::class, mappedBy: 'assigned_to')]
    private Collection $tasks;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twoFactorCode = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $two_factor_code_expires_at = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'members')]
    #[Groups(['user'])]
    #[MaxDepth(1)]
    private Collection $projects;

    #[ORM\Column(nullable: true)]
    #[Groups(['user', 'project'])]
    private ?bool $isPublic = null;

    #[ORM\OneToMany(mappedBy: 'sender', targetEntity: Invitation::class)]
    private Collection $sentInvitations;

    #[ORM\OneToMany(mappedBy: 'recipient', targetEntity: Invitation::class)]
    private Collection $receivedInvitations;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $verificationCode = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isVerified = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user'])]
    private ?bool $emailPublic = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user'])]
    private ?bool $addressPublic = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user'])]
    private ?bool $phonePublic = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user'])]
    private ?bool $sacemNumberPublic = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['user'])]
    private ?string $bio = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user'])]
    private ?bool $bioPublic = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user'])]
    private ?bool $rolesPublic = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user'])]
    private ?bool $projectsPublic = null;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
    private Collection $notifications;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'author')]
    private Collection $messages;

    /**
     * @var Collection<int, PushSubscription>
     */
    #[ORM\OneToMany(targetEntity: PushSubscription::class, mappedBy: 'user')]
    private Collection $pushSubscriptions;

    public function __construct()
    {
        $this->administrativeTasks = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->sentInvitations = new ArrayCollection();
        $this->receivedInvitations = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->pushSubscriptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function eraseCredentials(): void {}

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getSacemNumber(): ?string
    {
        return $this->sacemNumber;
    }

    public function setSacemNumber(?string $sacemNumber): static
    {
        $this->sacemNumber = $sacemNumber;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

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
            $administrativeTask->addAssignedTo($this);
        }

        return $this;
    }

    public function removeAdministrativeTask(AdministrativeTask $administrativeTask): static
    {
        if ($this->administrativeTasks->removeElement($administrativeTask)) {
            $administrativeTask->removeAssignedTo($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->addAssignedTo($this);
        }

        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task)) {
            $task->removeAssignedTo($this);
        }

        return $this;
    }

    public function getTwoFactorCode(): ?string
    {
        return $this->twoFactorCode;
    }

    public function setTwoFactorCode(?string $twoFactorCode): static
    {
        $this->twoFactorCode = $twoFactorCode;

        return $this;
    }

    public function getTwoFactorCodeExpiresAt(): ?\DateTimeImmutable
    {
        return $this->two_factor_code_expires_at;
    }

    public function setTwoFactorCodeExpiresAt(?\DateTimeImmutable $two_factor_code_expires_at): static
    {
        $this->two_factor_code_expires_at = $two_factor_code_expires_at;

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        $this->projects->removeElement($project);

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

    /**
     * @return Collection<int, Invitation>
     */
    public function getSentInvitations(): Collection
    {
        return $this->sentInvitations;
    }

    public function addSentInvitation(Invitation $invitation): static
    {
        if (!$this->sentInvitations->contains($invitation)) {
            $this->sentInvitations->add($invitation);
            $invitation->setSender($this);
        }

        return $this;
    }

    public function removeSentInvitation(Invitation $invitation): static
    {
        if ($this->sentInvitations->removeElement($invitation)) {
            // Set the owning side to null (unless already changed)
            if ($invitation->getSender() === $this) {
                $invitation->setSender(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function getReceivedInvitations(): Collection
    {
        return $this->receivedInvitations;
    }

    public function addReceivedInvitation(Invitation $invitation): static
    {
        if (!$this->receivedInvitations->contains($invitation)) {
            $this->receivedInvitations->add($invitation);
            $invitation->setRecipient($this);
        }

        return $this;
    }

    public function removeReceivedInvitation(Invitation $invitation): static
    {
        if ($this->receivedInvitations->removeElement($invitation)) {
            // Set the owning side to null (unless already changed)
            if ($invitation->getRecipient() === $this) {
                $invitation->setRecipient(null);
            }
        }

        return $this;
    }


    public function getVerificationCode(): ?string
    {
        return $this->verificationCode;
    }

    public function setVerificationCode(?string $verificationCode): static
    {
        $this->verificationCode = $verificationCode;

        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setVerified(?bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isEmailPublic(): ?bool
    {
        return $this->emailPublic;
    }

    public function setEmailPublic(?bool $emailPublic): static
    {
        $this->emailPublic = $emailPublic;

        return $this;
    }

    public function isAddressPublic(): ?bool
    {
        return $this->addressPublic;
    }

    public function setAddressPublic(?bool $addressPublic): static
    {
        $this->addressPublic = $addressPublic;

        return $this;
    }

    public function isPhonePublic(): ?bool
    {
        return $this->phonePublic;
    }

    public function setPhonePublic(?bool $phonePublic): static
    {
        $this->phonePublic = $phonePublic;

        return $this;
    }

    public function isSacemNumberPublic(): ?bool
    {
        return $this->sacemNumberPublic;
    }

    public function setSacemNumberPublic(?bool $sacemNumberPublic): static
    {
        $this->sacemNumberPublic = $sacemNumberPublic;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function isBioPublic(): ?bool
    {
        return $this->bioPublic;
    }

    public function setBioPublic(?bool $bioPublic): static
    {
        $this->bioPublic = $bioPublic;

        return $this;
    }

    public function isRolesPublic(): ?bool
    {
        return $this->rolesPublic;
    }

    public function setRolesPublic(?bool $rolesPublic): static
    {
        $this->rolesPublic = $rolesPublic;

        return $this;
    }

    public function isProjectsPublic(): ?bool
    {
        return $this->projectsPublic;
    }

    public function setProjectsPublic(?bool $projectsPublic): static
    {
        $this->projectsPublic = $projectsPublic;

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setAuthor($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getAuthor() === $this) {
                $message->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PushSubscription>
     */
    public function getPushSubscriptions(): Collection
    {
        return $this->pushSubscriptions;
    }

    public function addPushSubscription(PushSubscription $pushSubscription): static
    {
        if (!$this->pushSubscriptions->contains($pushSubscription)) {
            $this->pushSubscriptions->add($pushSubscription);
            $pushSubscription->setUser($this);
        }

        return $this;
    }

    public function removePushSubscription(PushSubscription $pushSubscription): static
    {
        if ($this->pushSubscriptions->removeElement($pushSubscription)) {
            // set the owning side to null (unless already changed)
            if ($pushSubscription->getUser() === $this) {
                $pushSubscription->setUser(null);
            }
        }

        return $this;
    }
}
