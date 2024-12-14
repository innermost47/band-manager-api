<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class NotificationService
{
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security
    ) {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function notifyProjectMembers(
        string $content,
        string $type,
        string $frontEndUrl,
        Project $project,
        array $metadata = [],
        ?User $excludeUser = null
    ): void {
        $excludeUser = $excludeUser ?? $this->security->getUser();

        foreach ($project->getMembers() as $member) {
            if ($excludeUser && $member->getId() === $excludeUser->getId()) {
                continue;
            }

            $notification = new Notification();
            $notification->setUser($member);
            $notification->setContent($content);
            $notification->setType($type);
            $notification->setFrontEndUrl($frontEndUrl);
            $notification->setProject($project);
            $notification->setMetadata($metadata);
            $notification->setHasSeen(false);
            $notification->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($notification);
        }

        $this->entityManager->flush();
    }

    public function createSingleNotification(
        User $user,
        string $content,
        string $type,
        string $frontEndUrl,
        ?Project $project = null,
        array $metadata = []
    ): void {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setContent($content);
        $notification->setType($type);
        $notification->setFrontEndUrl($frontEndUrl);
        $notification->setProject($project);
        $notification->setMetadata($metadata);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}
