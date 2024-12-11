<?php

namespace App\Controller;

use App\Entity\AdministrativeTask;
use App\Repository\AdministrativeTaskRepository;
use App\Repository\ProjectRepository;
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
    private $projectRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        AdministrativeTaskRepository $repository,
        ValidatorInterface $validator,
        ProjectRepository $projectRepository
    ) {
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->validator = $validator;
        $this->projectRepository = $projectRepository;
    }

    private function verifyProjectAccess($project): void
    {
        $currentUser = $this->getUser();
        if (!$currentUser || !$project->getMembers()->contains($currentUser)) {
            throw $this->createAccessDeniedException('Access denied to this project.');
        }
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        try {
            $currentUser = $this->getUser();

            if (!$currentUser) {
                return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
            }

            try {
                $projects = $this->projectRepository->findByMember($currentUser);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Failed to retrieve projects', 'details' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }

            try {
                $tasks = $this->repository->findByProject($projects);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Failed to retrieve tasks', 'details' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }

            try {
                $taskResponse = array_map(function ($task) {
                    return [
                        'id' => $task->getId(),
                        'name' => $task->getName(),
                        'description' => $task->getDescription(),
                        'project' => [
                            'id' => $task->getProject()->getId(),
                            'name' => $task->getProject()->getName(),
                        ],
                        'tableStructure' => $task->getTableStructure(),
                        'tableValues' => $task->getTableValues(),
                        'createdAt' => $task->getCreatedAt(),
                        'completedAt' => $task->getCompletedAt(),
                    ];
                }, $tasks);

                $projectResponse = array_map(function ($project) {
                    return [
                        'id' => $project->getId(),
                        'name' => $project->getName(),
                    ];
                }, $projects);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Failed to process data', 'details' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->json(
                [
                    'tasks' => $taskResponse,
                    'projects' => $projectResponse,
                ],
                JsonResponse::HTTP_OK
            );
        } catch (\Exception $e) {
            return $this->json(['error' => 'Unexpected error occurred', 'details' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $task = $this->repository->find($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->verifyProjectAccess($task->getProject());

        return $this->json($task, JsonResponse::HTTP_OK, [], ['groups' => 'administrative_task']);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['project_id'])) {
            return $this->json(['error' => 'Project ID is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $project = $this->projectRepository->find($data['project_id']);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->verifyProjectAccess($project);

        $task = new AdministrativeTask();
        $task->setName(trim($data['name']));
        $task->setTableStructure($data['tableStructure']);
        $task->setTableValues($data['tableValues']);
        $task->setDescription(isset($data['description']) ? trim($data['description']) : null);
        $task->setCreatedAt(new \DateTimeImmutable());
        $task->setCompletedAt(null);
        $task->setProject($project);

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

        $this->verifyProjectAccess($task->getProject());

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $task->setName(isset($data['name']) ? trim($data['name']) : $task->getName());
        $task->setDescription(isset($data['description']) ? trim($data['description']) : $task->getDescription());
        $task->setTableStructure(isset($data['tableStructure']) ? $data['tableStructure'] : $task->getTableStructure());
        $task->setTableValues(isset($data['tableValues']) ? $data['tableValues'] : $task->getTableValues());

        $errors = $this->validator->validate($task);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($task, JsonResponse::HTTP_OK, [], ['groups' => 'administrative_task']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $task = $this->repository->find($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->verifyProjectAccess($task->getProject());

        $this->entityManager->remove($task);
        $this->entityManager->flush();

        return $this->json(['message' => 'Task deleted successfully'], JsonResponse::HTTP_OK);
    }
}
