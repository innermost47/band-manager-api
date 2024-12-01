<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/projects', name: 'project_')]
class ProjectController extends AbstractController
{
    private $entityManager;
    private $repository;
    private $validator;
    private $songRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProjectRepository $repository,
        ValidatorInterface $validator,
        SongRepository $songRepository
    ) {
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->validator = $validator;
        $this->songRepository = $songRepository;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $projects = $this->repository->findAll();
        return $this->json($projects, JsonResponse::HTTP_OK, [], ['groups' => 'project']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $project = $this->repository->find($id);

        if (!$project) {
            return $this->json(['error' => 'Project not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $songs = $this->songRepository->findBy(['project' => $project]);

        $data = [
            'project' => $project,
            'songs' => $songs,
        ];

        return $this->json($data, JsonResponse::HTTP_OK, [], ['groups' => ['project', 'song']]);
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

        if (isset($data['description']) && strlen($data['description']) > 2000) {
            return $this->json(['error' => 'Description must not exceed 2000 characters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $project = new Project();
        $project->setName(trim($data['name']));
        $project->setDescription(isset($data['description']) ? trim($data['description']) : null);

        $errors = $this->validator->validate($project);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $this->json($project, JsonResponse::HTTP_CREATED, [], ['groups' => 'project']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $project = $this->repository->find($id);

        if (!$project) {
            return $this->json(['error' => 'Project not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['name']) && empty(trim($data['name']))) {
            return $this->json(['error' => 'Name cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['description']) && strlen($data['description']) > 2000) {
            return $this->json(['error' => 'Description must not exceed 2000 characters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $project->setName(isset($data['name']) ? trim($data['name']) : $project->getName());
        $project->setDescription(isset($data['description']) ? trim($data['description']) : $project->getDescription());

        $errors = $this->validator->validate($project);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($project, JsonResponse::HTTP_OK, [], ['groups' => 'project']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $project = $this->repository->find($id);

        if (!$project) {
            return $this->json(['error' => 'Project not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();

        return $this->json(['message' => 'Project deleted successfully'], JsonResponse::HTTP_OK);
    }
}
