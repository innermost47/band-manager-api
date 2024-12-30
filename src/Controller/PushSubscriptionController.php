<?php

namespace App\Controller;

use App\Entity\PushSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PushSubscriptionController extends AbstractController
{
    #[Route('/api/push/subscribe', methods: ['POST'])]
    public function subscribe(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

        $existingSubscription = $em->getRepository(PushSubscription::class)
            ->findOneBy(['endpoint' => $data['endpoint'], 'user' => $user]);

        if (!$existingSubscription) {
            $subscription = new PushSubscription();
            $subscription->setUser($user);
            $subscription->setEndpoint($data['endpoint']);
            $subscription->setP256dhKey($data['keys']['p256dh']);
            $subscription->setAuthToken($data['keys']['auth']);

            $em->persist($subscription);
            $em->flush();
        }

        return new JsonResponse(['status' => 'subscribed']);
    }
}
