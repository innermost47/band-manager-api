<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\EventException;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/events')]
class EventController extends AbstractController
{
    private $projectRepository;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        ProjectRepository $projectRepository,
    ) {
        $this->projectRepository = $projectRepository;
    }

    #[Route('/project/{projectId}', name: 'api_events_by_project', methods: ['GET'])]
    public function getByProject(int $projectId): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);
        $currentUser = $this->getUser();

        if (!$project) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $isOwner = $project->getMembers()->contains($currentUser);
        if (!$isOwner) {
            return $this->json(['error' => 'Access denied'], JsonResponse::HTTP_NOT_FOUND);
        }

        $events = $this->entityManager->getRepository(Event::class)
            ->findEventsByProject($project);

        return $this->json($events, Response::HTTP_OK, [], [
            'groups' => ['event:read']
        ]);
    }

    #[Route('/public/project/{projectId}', name: 'api_public_events_by_project', methods: ['GET'])]
    public function getPublicEventsByProject(int $projectId): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);

        if (!$project) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$project->isPublic()) {
            return $this->json(['error' => 'Project is not public'], Response::HTTP_FORBIDDEN);
        }

        $events = $this->entityManager->getRepository(Event::class)
            ->findPublicEventsByProject($project);

        return $this->json($events, Response::HTTP_OK, [], [
            'groups' => ['event:read']
        ]);
    }

    #[Route('/public', name: 'api_public_events', methods: ['GET'])]
    public function getAllPublicEvents(): JsonResponse
    {

        $events = $this->entityManager->getRepository(Event::class)
            ->findAllPublicEvents();

        return $this->json($events, Response::HTTP_OK, [], [
            'groups' => ['event:read']
        ]);
    }

    #[Route('', name: 'api_events_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $currentUser = $this->getUser();

            $project = $this->projectRepository->find($data['project'] ?? null);
            if (!$project) {
                return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
            }

            $isOwner = $project->getMembers()->contains($currentUser);
            if (!$isOwner) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $event = $this->serializer->deserialize(
                $request->getContent(),
                Event::class,
                'json'
            );

            if (isset($data['is_public'])) {
                $event->setPublic($data['is_public']);
            }

            $event->setProject($project);

            $errors = $this->validator->validate($event);
            if (count($errors) > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            return $this->json($event, Response::HTTP_CREATED, [], [
                'groups' => ['event:read']
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_events_update', methods: ['PUT'])]
    public function update(Request $request, Event $event): JsonResponse
    {
        try {
            $currentUser = $this->getUser();
            $project = $event->getProject();

            if (!$project->getMembers()->contains($currentUser)) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            $context = [
                'object_to_populate' => $event,
                'groups' => ['event:write'],
                'allow_extra_attributes' => true
            ];

            $this->serializer->deserialize(
                $request->getContent(),
                Event::class,
                'json',
                $context
            );

            if (isset($data['is_public'])) {
                $event->setPublic($data['is_public']);
            }

            $errors = $this->validator->validate($event);
            if (count($errors) > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $event->setProject($project);

            $this->entityManager->flush();

            return $this->json($event, Response::HTTP_OK, [], [
                'groups' => ['event:read']
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_events_delete', methods: ['DELETE'])]
    public function delete(Event $event): JsonResponse
    {
        $currentUser = $this->getUser();
        $project = $event->getProject();

        if (!$project->getMembers()->contains($currentUser)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($event);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/cancel', name: 'api_events_cancel_occurrence', methods: ['POST'])]
    public function cancelOccurrence(Request $request, Event $event): JsonResponse
    {
        try {
            $currentUser = $this->getUser();
            $project = $event->getProject();

            if (!$project->getMembers()->contains($currentUser)) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            $date = new \DateTimeImmutable($data['date']);
            $reason = $data['reason'] ?? 'Annulé';

            $exception = new EventException();
            $exception->setParentEvent($event)
                ->setExceptionDate($date)
                ->setIsCancelled(true)
                ->setReason($reason);

            $this->entityManager->persist($exception);
            $this->entityManager->flush();

            return $this->json($exception, Response::HTTP_CREATED, [], [
                'groups' => ['event:read']
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/reschedule', name: 'api_events_reschedule_occurrence', methods: ['POST'])]
    public function rescheduleOccurrence(Request $request, Event $event): JsonResponse
    {
        try {
            $currentUser = $this->getUser();
            $project = $event->getProject();

            if (!$project->getMembers()->contains($currentUser)) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            $originalDate = new \DateTimeImmutable($data['original_date']);
            $newStart = new \DateTimeImmutable($data['new_start']);
            $newEnd = new \DateTimeImmutable($data['new_end']);

            $exception = new EventException();
            $exception->setParentEvent($event)
                ->setExceptionDate($originalDate)
                ->setRescheduledStart($newStart)
                ->setRescheduledEnd($newEnd);

            $this->entityManager->persist($exception);
            $this->entityManager->flush();

            return $this->json($exception, Response::HTTP_CREATED, [], [
                'groups' => ['event:read']
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
