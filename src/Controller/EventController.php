<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;

#[Route('/api/events', name: 'event_')]
class EventController extends AbstractController
{
    private $entityManager;
    private $repository;
    private $validator;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventRepository $repository,
        ValidatorInterface $validator
    ) {
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->validator = $validator;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $events = $this->repository->findAll();

        return $this->json($events, JsonResponse::HTTP_OK, [], ['groups' => 'event']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $event = $this->repository->find($id);

        if (!$event) {
            return $this->json(['error' => 'Event not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($event, JsonResponse::HTTP_OK, [], ['groups' => 'event']);
    }


    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['name'], $data['start_date'], $data['end_date'], $data['location'])) {
            return $this->json(['error' => 'Missing required fields'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $startDate = new \DateTimeImmutable($data['start_date']);
            $endDate = new \DateTimeImmutable($data['end_date']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($startDate > $endDate) {
            return $this->json(['error' => 'Start date must be before end date'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $event = new Event();
        $event->setName(trim($data['name']));
        $event->setDescription(trim($data['description'] ?? ''));
        $event->setStartDate($startDate);
        $event->setEndDate($endDate);
        $event->setLocation(trim($data['location']));
        $event->setRecurrence($data['recurrence'] ?? null);

        $entityManager->persist($event);
        $entityManager->flush();

        $participants = $userRepository->findAll();
        foreach ($participants as $participant) {
            $email = (new Email())
                ->from($_ENV['MAILER_FROM'])
                ->to($participant->getEmail())
                ->subject("New Event: {$event->getName()}")
                ->text("A new event has been created: {$event->getName()}.\nStart: {$event->getStartDate()->format('Y-m-d H:i')}.\nLocation: {$event->getLocation()}.");

            $mailer->send($email);
        }

        if ($event->getRecurrence()) {
            $this->generateRecurringEvents($event, $entityManager);
        }

        return $this->json($event, JsonResponse::HTTP_CREATED, [], ['groups' => 'event']);
    }

    private function generateRecurringEvents(Event $event, EntityManagerInterface $entityManager)
    {
        $recurrenceMap = [
            'weekly' => '+1 week',
            'biweekly' => '+2 weeks',
            'monthly' => '+1 month',
        ];

        $recurrenceInterval = $recurrenceMap[$event->getRecurrence()] ?? null;
        if (!$recurrenceInterval) {
            return;
        }

        $currentDate = $event->getStartDate();
        $endDate = $event->getEndDate();
        for ($i = 1; $i <= 10; $i++) {
            $currentDate = $currentDate->modify($recurrenceInterval);
            $newEvent = clone $event;
            $newEvent->setStartDate($currentDate);
            $newEvent->setEndDate($endDate->modify($recurrenceInterval));

            $entityManager->persist($newEvent);
        }

        $entityManager->flush();
    }


    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $event = $this->repository->find($id);

        if (!$event) {
            return $this->json(['error' => 'Event not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['name']) && empty(trim($data['name']))) {
            return $this->json(['error' => 'Name cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['description']) && empty(trim($data['description']))) {
            return $this->json(['error' => 'Description cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['start_date']) && isset($data['end_date'])) {
            try {
                $startDate = new \DateTimeImmutable($data['start_date']);
                $endDate = new \DateTimeImmutable($data['end_date']);
                if ($startDate > $endDate) {
                    return $this->json(['error' => 'Start date must be before end date'], JsonResponse::HTTP_BAD_REQUEST);
                }
                $event->setStartDate($startDate);
                $event->setEndDate($endDate);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Invalid date format'], JsonResponse::HTTP_BAD_REQUEST);
            }
        }

        $event->setName($data['name'] ?? $event->getName());
        $event->setDescription($data['description'] ?? $event->getDescription());
        $event->setLocation($data['location'] ?? $event->getLocation());

        $errors = $this->validator->validate($event);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($event, JsonResponse::HTTP_OK, [], ['groups' => 'event']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $event = $this->repository->find($id);

        if (!$event) {
            return $this->json(['error' => 'Event not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($event);
        $this->entityManager->flush();

        return $this->json(['message' => 'Event deleted successfully'], JsonResponse::HTTP_OK);
    }
}
