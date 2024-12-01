<?php

namespace App\Controller;

use App\Entity\Column;
use App\Entity\Board;
use App\Repository\ColumnRepository;
use App\Repository\BoardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/columns', name: 'column_')]
class ColumnController extends AbstractController
{
    private $entityManager;
    private $repository;
    private $validator;
    private $boardRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ColumnRepository $repository,
        ValidatorInterface $validator,
        BoardRepository $boardRepository
    ) {
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->validator = $validator;
        $this->boardRepository = $boardRepository;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $columns = $this->repository->findAll();

        return $this->json($columns, JsonResponse::HTTP_OK, [], ['groups' => 'column']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $column = $this->repository->find($id);

        if (!$column) {
            return $this->json(['error' => 'Column not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($column, JsonResponse::HTTP_OK, [], ['groups' => 'column']);
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

        if (!isset($data['board_id']) || empty($data['board_id'])) {
            return $this->json(['error' => 'Board ID is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $board = $this->boardRepository->find($data['board_id']);
        if (!$board) {
            return $this->json(['error' => 'Board not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $column = new Column();
        $column->setName(trim($data['name']));
        $column->setBoard($board);
        $column->setPosition($data['position'] ?? 0);

        $errors = $this->validator->validate($column);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($column);
        $this->entityManager->flush();

        return $this->json($column, JsonResponse::HTTP_CREATED, [], ['groups' => 'column']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $column = $this->repository->find($id);

        if (!$column) {
            return $this->json(['error' => 'Column not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['name']) && empty(trim($data['name']))) {
            return $this->json(['error' => 'Name cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['board_id'])) {
            $board = $this->boardRepository->find($data['board_id']);
            if (!$board) {
                return $this->json(['error' => 'Board not found'], JsonResponse::HTTP_NOT_FOUND);
            }
            $column->setBoard($board);
        }

        $column->setName(isset($data['name']) ? trim($data['name']) : $column->getName());
        $column->setPosition($data['position'] ?? $column->getPosition());

        $errors = $this->validator->validate($column);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($column, JsonResponse::HTTP_OK, [], ['groups' => 'column']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $column = $this->repository->find($id);

        if (!$column) {
            return $this->json(['error' => 'Column not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($column);
        $this->entityManager->flush();

        return $this->json(['message' => 'Column deleted successfully'], JsonResponse::HTTP_OK);
    }
}
