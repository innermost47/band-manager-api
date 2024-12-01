<?php

namespace App\Controller;

use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Repository\ColumnRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tasks', name: 'task_')]
class TaskController extends AbstractController
{
    private $entityManager;
    private $taskRepository;
    private $columnRepository;
    private $validator;

    public function __construct(
        EntityManagerInterface $entityManager,
        TaskRepository $taskRepository,
        ColumnRepository $columnRepository,
        ValidatorInterface $validator
    ) {
        $this->entityManager = $entityManager;
        $this->taskRepository = $taskRepository;
        $this->columnRepository = $columnRepository;
        $this->validator = $validator;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $tasks = $this->taskRepository->findAll();

        return $this->json($tasks, JsonResponse::HTTP_OK, [], ['groups' => 'task']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($task, JsonResponse::HTTP_OK, [], ['groups' => 'task']);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['title']) || empty(trim($data['title']))) {
            return $this->json(['error' => 'Title is required and cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['description']) && strlen($data['description']) > 1000) {
            return $this->json(['error' => 'Description must not exceed 1000 characters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['column_id'])) {
            return $this->json(['error' => 'Column ID is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $column = $this->columnRepository->find($data['column_id']);
        if (!$column) {
            return $this->json(['error' => 'Column not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $task = new Task();
        $task->setTitle(trim($data['title']));
        $task->setDescription(isset($data['description']) ? trim($data['description']) : null);
        $task->setPosition($data['position'] ?? 0);
        $task->setColumn($column);
        $task->setCreatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($task);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $this->json($task, JsonResponse::HTTP_CREATED, [], ['groups' => 'task']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['title']) && empty(trim($data['title']))) {
            return $this->json(['error' => 'Title cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['description']) && strlen($data['description']) > 1000) {
            return $this->json(['error' => 'Description must not exceed 1000 characters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['column_id'])) {
            $column = $this->columnRepository->find($data['column_id']);
            if (!$column) {
                return $this->json(['error' => 'Column not found'], JsonResponse::HTTP_NOT_FOUND);
            }
            $task->setColumn($column);
        }

        $task->setTitle(isset($data['title']) ? trim($data['title']) : $task->getTitle());
        $task->setDescription(isset($data['description']) ? trim($data['description']) : $task->getDescription());
        $task->setPosition($data['position'] ?? $task->getPosition());
        $task->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($task);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($task, JsonResponse::HTTP_OK, [], ['groups' => 'task']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($task);
        $this->entityManager->flush();

        return $this->json(['message' => 'Task deleted successfully'], JsonResponse::HTTP_OK);
    }
}
