<?php

namespace App\Controller;

use App\Entity\AdministrativeTask;
use App\Repository\AdministrativeTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/administrative-tasks', name: 'administrative_task_')]
class AdministrativeTaskController extends AbstractController
{
    private $entityManager;
    private $repository;
    private $validator;

    public function __construct(EntityManagerInterface $entityManager, AdministrativeTaskRepository $repository, ValidatorInterface $validator)
    {
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->validator = $validator;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $tasks = $this->repository->findAll();

        return $this->json($tasks, JsonResponse::HTTP_OK, [], ['groups' => 'administrative_task']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $task = $this->repository->find($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($task, JsonResponse::HTTP_OK, [], ['groups' => 'administrative_task']);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['name']) || empty(trim($data['name']))) {
            return $this->json(['error' => 'Name is required and cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['description']) && strlen($data['description']) > 1000) {
            return $this->json(['error' => 'Description must not exceed 1000 characters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $task = new AdministrativeTask();
        $task->setName(trim($data['name']));
        $task->setDescription(isset($data['description']) ? trim($data['description']) : null);
        $task->setCreatedAt(new \DateTimeImmutable());
        $task->setCompletedAt(null);

        $errors = $this->validator->validate($task);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $this->json($task, JsonResponse::HTTP_CREATED, [], ['groups' => 'administrative_task']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $task = $this->repository->find($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['name']) && empty(trim($data['name']))) {
            return $this->json(['error' => 'Name cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['description']) && strlen($data['description']) > 1000) {
            return $this->json(['error' => 'Description must not exceed 1000 characters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $task->setName(isset($data['name']) ? trim($data['name']) : $task->getName());
        $task->setDescription(isset($data['description']) ? trim($data['description']) : $task->getDescription());

        $errors = $this->validator->validate($task);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($task, JsonResponse::HTTP_OK, [], ['groups' => 'administrative_task']);
    }

    #[Route('/{id}/complete', name: 'complete', methods: ['PATCH'])]
    public function complete(int $id): JsonResponse
    {
        $task = $this->repository->find($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($task->getCompletedAt() !== null) {
            return $this->json(['error' => 'Task is already completed'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $task->setCompletedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json(['message' => 'Task marked as completed'], JsonResponse::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $task = $this->repository->find($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($task);
        $this->entityManager->flush();

        return $this->json(['message' => 'Task deleted successfully'], JsonResponse::HTTP_OK);
    }
}
