<?php

namespace App\Controller;

use App\Entity\Board;
use App\Repository\BoardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/boards', name: 'boards_')]
class BoardController extends AbstractController
{
    private $entityManager;
    private $repository;
    private $validator;

    public function __construct(EntityManagerInterface $entityManager, BoardRepository $repository, ValidatorInterface $validator)
    {
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->validator = $validator;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $boards = $this->repository->findAll();

        return $this->json($boards, JsonResponse::HTTP_OK, [], ['groups' => 'board']);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['name']) || empty(trim($data['name']))) {
            return $this->json(['error' => 'Invalid board name'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $board = new Board();
        $board->setName(trim($data['name']));
        $board->setDescription(isset($data['description']) ? trim($data['description']) : null);

        $errors = $this->validator->validate($board);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($board);
        $this->entityManager->flush();

        return $this->json($board, JsonResponse::HTTP_CREATED, [], ['groups' => 'board']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $board = $this->repository->find($id);

        if (!$board) {
            return $this->json(['error' => 'Board not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($board, JsonResponse::HTTP_OK, [], ['groups' => 'board']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $board = $this->repository->find($id);

        if (!$board) {
            return $this->json(['error' => 'Board not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data || (isset($data['name']) && empty(trim($data['name'])))) {
            return $this->json(['error' => 'Invalid board name'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['name'])) {
            $board->setName(trim($data['name']));
        }
        if (isset($data['description'])) {
            $board->setDescription(trim($data['description']));
        }

        $errors = $this->validator->validate($board);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($board, JsonResponse::HTTP_OK, [], ['groups' => 'board']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $board = $this->repository->find($id);

        if (!$board) {
            return $this->json(['error' => 'Board not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($board);
        $this->entityManager->flush();

        return $this->json(['message' => 'Board deleted successfully'], JsonResponse::HTTP_OK);
    }
}
