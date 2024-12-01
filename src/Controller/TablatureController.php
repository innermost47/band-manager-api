<?php

namespace App\Controller;

use App\Entity\Tablature;
use App\Repository\TablatureRepository;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tablatures', name: 'tablature_')]
class TablatureController extends AbstractController
{
    private $entityManager;
    private $tablatureRepository;
    private $songRepository;
    private $validator;

    public function __construct(
        EntityManagerInterface $entityManager,
        TablatureRepository $tablatureRepository,
        SongRepository $songRepository,
        ValidatorInterface $validator
    ) {
        $this->entityManager = $entityManager;
        $this->tablatureRepository = $tablatureRepository;
        $this->songRepository = $songRepository;
        $this->validator = $validator;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $tablatures = $this->tablatureRepository->findAll();

        return $this->json($tablatures, JsonResponse::HTTP_OK, [], ['groups' => 'tablature']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $tablature = $this->tablatureRepository->find($id);

        if (!$tablature) {
            return $this->json(['error' => 'Tablature not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($tablature, JsonResponse::HTTP_OK, [], ['groups' => 'tablature']);
    }

    #[Route('/by-song/{songId}', name: 'list_by_song', methods: ['GET'])]
    public function listBySong(int $songId): JsonResponse
    {
        $song = $this->songRepository->find($songId);

        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $tablatures = $this->tablatureRepository->findBy(['song_id' => $songId]);

        return $this->json($tablatures, JsonResponse::HTTP_OK, [], ['groups' => 'tablature']);
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

        if (!isset($data['song_id'])) {
            return $this->json(['error' => 'Song ID is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['instrument'])) {
            return $this->json(['error' => 'Instrument is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $song = $this->songRepository->find($data['song_id']);
        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $tablature = new Tablature();
        $tablature->setTitle(trim($data['title']));
        $tablature->setContent(isset($data['content']) ? $data['content'] : null);
        $tablature->setInstrument(trim($data['instrument']));
        $tablature->setSong($song);
        $tablature->setCreatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($tablature);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($tablature);
        $this->entityManager->flush();

        return $this->json($tablature, JsonResponse::HTTP_CREATED, [], ['groups' => 'tablature']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $tablature = $this->tablatureRepository->find($id);

        if (!$tablature) {
            return $this->json(['error' => 'Tablature not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['title']) && empty(trim($data['title']))) {
            return $this->json(['error' => 'Title cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $tablature->setTitle(isset($data['title']) ? trim($data['title']) : $tablature->getTitle());
        $tablature->setContent(isset($data['content']) ? $data['content'] : $tablature->getContent());
        $tablature->setInstrument(isset($data['instrument']) ? $data['instrument'] : $tablature->getInstrument());
        $tablature->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($tablature);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($tablature, JsonResponse::HTTP_OK, [], ['groups' => 'tablature']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $tablature = $this->tablatureRepository->find($id);

        if (!$tablature) {
            return $this->json(['error' => 'Tablature not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($tablature);
        $this->entityManager->flush();

        return $this->json(['message' => 'Tablature deleted successfully'], JsonResponse::HTTP_OK);
    }
}
