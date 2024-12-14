<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    private $notificationRepository;
    private $entityManager;
    private $serializer;

    public function __construct(
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ) {
        $this->notificationRepository = $notificationRepository;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    #[Route('', name: 'get_notifications', methods: ['GET'])]
    public function getNotifications(): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $notifications = $this->notificationRepository->findBy(
            ['user' => $currentUser],
            ['createdAt' => 'DESC'],
            50  // Limite aux 50 dernières notifications
        );

        return $this->json(
            $notifications,
            JsonResponse::HTTP_OK,
            [],
            ['groups' => 'notification:read']
        );
    }

    #[Route('/unread', name: 'get_unread_notifications', methods: ['GET'])]
    public function getUnreadNotifications(): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $notifications = $this->notificationRepository->findBy(
            ['user' => $currentUser, 'hasSeen' => false],
            ['createdAt' => 'DESC']
        );

        return $this->json(
            $notifications,
            JsonResponse::HTTP_OK,
            [],
            ['groups' => 'notification:read']
        );
    }

    #[Route('/{id<\d+>}/mark-as-read', name: 'mark_notification_as_read', methods: ['PUT'])]
    public function markAsRead(int $id): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $notification = $this->notificationRepository->findOneBy([
            'id' => $id,
            'user' => $currentUser
        ]);

        if (!$notification) {
            return $this->json(
                ['error' => 'Notification not found'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $notification->setHasSeen(true);
        $this->entityManager->flush();

        return $this->json(
            ['message' => 'Notification marked as read'],
            JsonResponse::HTTP_OK
        );
    }

    #[Route('/mark-all-as-read', name: 'mark_all_notifications_as_read', methods: ['PUT'])]
    public function markAllAsRead(): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $unreadNotifications = $this->notificationRepository->findBy([
            'user' => $currentUser,
            'hasSeen' => false
        ]);

        foreach ($unreadNotifications as $notification) {
            $notification->setHasSeen(true);
        }

        $this->entityManager->flush();

        return $this->json(
            [
                'message' => 'All notifications marked as read',
                'count' => count($unreadNotifications)
            ],
            JsonResponse::HTTP_OK
        );
    }

    #[Route('/count-unread', name: 'count_unread_notifications', methods: ['GET'])]
    public function countUnreadNotifications(): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $count = $this->notificationRepository->count([
            'user' => $currentUser,
            'hasSeen' => false
        ]);

        return $this->json(['count' => $count], JsonResponse::HTTP_OK);
    }

    #[Route('/all', name: 'delete_all_notifications', methods: ['DELETE'])]
    public function deleteAllNotifications(): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $notifications = $this->notificationRepository->findBy(['user' => $currentUser]);

        if (empty($notifications)) {
            return $this->json(
                ['message' => 'No notifications to delete'],
                JsonResponse::HTTP_OK
            );
        }

        foreach ($notifications as $notification) {
            $this->entityManager->remove($notification);
        }
        $this->entityManager->flush();

        return $this->json(
            ['message' => 'All notifications deleted successfully'],
            JsonResponse::HTTP_OK
        );
    }

    #[Route('/{id<\d+>}', name: 'delete_notification', methods: ['DELETE'])]
    public function deleteNotification(int $id): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $notification = $this->notificationRepository->findOneBy([
            'id' => $id,
            'user' => $currentUser
        ]);

        if (!$notification) {
            return $this->json(
                ['error' => 'Notification not found'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $this->entityManager->remove($notification);
        $this->entityManager->flush();

        return $this->json(
            ['message' => 'Notification deleted successfully'],
            JsonResponse::HTTP_OK
        );
    }
}
