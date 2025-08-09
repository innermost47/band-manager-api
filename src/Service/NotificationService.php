<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\PushSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class NotificationService
{
    private EntityManagerInterface $entityManager;
    private Security $security;
    private WebPush $webPush;

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        #[Autowire('%env(VAPID_PUBLIC_KEY)%')] string $vapidPublicKey,
        #[Autowire('%env(VAPID_PRIVATE_KEY)%')] string $vapidPrivateKey,
        #[Autowire('%env(MAILER_USERNAME)%')] string $mailerUsername
    ) {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $auth = [
            'VAPID' => [
                'subject' => 'mailto:' . $mailerUsername,
                'publicKey' => $vapidPublicKey,
                'privateKey' => $vapidPrivateKey,
            ],
        ];

        $this->webPush = new WebPush($auth);
    }

    private function pushNotification(User $user, Notification $notification, ?User $excludeUser = null): void
    {
        if ($excludeUser !== null && $user->getId() === $excludeUser->getId()) {
            return;
        }
        $subscriptions = $this->entityManager->getRepository(PushSubscription::class)
            ->findBy(['user' => $user]);

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->getEndpoint(),
                'keys' => [
                    'p256dh' => $sub->getP256dhKey(),
                    'auth' => $sub->getAuthToken()
                ]
            ]);

            $payload = json_encode([
                'title' => 'New notification',
                'body' => $notification->getContent(),
                'url' => $notification->getFrontEndUrl(),
                'type' => $notification->getType()
            ]);

            $this->webPush->queueNotification($subscription, $payload);
        }
        foreach ($this->webPush->flush() as $report) {
            if (!$report->isSuccess()) {
            }
        }
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
            if ($excludeUser !== null && $member->getId() === $excludeUser->getId()) {
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
            $this->pushNotification($member, $notification, $excludeUser);
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
        $notification->setHasSeen(false);
        $notification->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        $this->pushNotification($user, $notification);
    }
}
