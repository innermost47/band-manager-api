<?php

namespace App\Controller;

use App\Entity\Lyrics;
use App\Repository\LyricsRepository;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/lyrics', name: 'lyrics_')]
class LyricsController extends AbstractController
{
    private $entityManager;
    private $repository;
    private $validator;
    private $songRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        LyricsRepository $repository,
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
        $lyrics = $this->repository->findAll();

        return $this->json($lyrics, JsonResponse::HTTP_OK, [], ['groups' => 'lyrics']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $lyric = $this->repository->find($id);

        if (!$lyric) {
            return $this->json(['error' => 'Lyrics not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($lyric, JsonResponse::HTTP_OK, [], ['groups' => 'lyrics']);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['content']) || empty(trim($data['content']))) {
            return $this->json(['error' => 'Content is required and cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['song_id'])) {
            return $this->json(['error' => 'Song ID is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $song = $this->songRepository->find($data['song_id']);
        if (!$song) {
            return $this->json(['error' => 'Invalid song ID'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $lyrics = new Lyrics();
        $lyrics->setContent(trim($data['content']));
        $lyrics->setSong($song);
        $lyrics->setCreatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($lyrics);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($lyrics);
        $this->entityManager->flush();

        return $this->json($lyrics, JsonResponse::HTTP_CREATED, [], ['groups' => 'lyrics']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $lyrics = $this->repository->find($id);

        if (!$lyrics) {
            return $this->json(['error' => 'Lyrics not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['content']) && empty(trim($data['content']))) {
            return $this->json(['error' => 'Content cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['song_id'])) {
            $song = $this->songRepository->find($data['song_id']);
            if (!$song) {
                return $this->json(['error' => 'Invalid song ID'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $lyrics->setSongId($song);
        }

        $lyrics->setContent(isset($data['content']) ? trim($data['content']) : $lyrics->getContent());
        $lyrics->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($lyrics);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($lyrics, JsonResponse::HTTP_OK, [], ['groups' => 'lyrics']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $lyrics = $this->repository->find($id);

        if (!$lyrics) {
            return $this->json(['error' => 'Lyrics not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($lyrics);
        $this->entityManager->flush();

        return $this->json(['message' => 'Lyrics deleted successfully'], JsonResponse::HTTP_OK);
    }

    #[Route('/song/{songId}', name: 'list_by_song', methods: ['GET'])]
    public function listBySong(int $songId): JsonResponse
    {
        $song = $this->songRepository->find($songId);

        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $lyrics = $this->repository->findBy(['song_id' => $song]);

        return $this->json($lyrics, JsonResponse::HTTP_OK, [], ['groups' => 'lyrics']);
    }
}
