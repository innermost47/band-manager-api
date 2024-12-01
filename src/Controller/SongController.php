<?php

namespace App\Controller;

use App\Entity\Song;
use App\Entity\Lyrics;
use App\Entity\AudioFileType;
use App\Repository\SongRepository;
use App\Repository\ProjectRepository;
use App\Repository\AudioFileRepository;
use App\Repository\AudioFileTypeRepository;
use App\Repository\TablatureRepository;
use App\Repository\LyricsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/songs', name: 'song_')]
class SongController extends AbstractController
{
    private $entityManager;
    private $songRepository;
    private $audioFileRepository;
    private $validator;
    private $projectRepository;
    private $tablatureRepository;
    private $lyricsRepository;
    private $audioFileTypeRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        SongRepository $songRepository,
        AudioFileRepository $audioFileRepository,
        ProjectRepository $projectRepository,
        ValidatorInterface $validator,
        TablatureRepository $tablatureRepository,
        LyricsRepository $lyricsRepository,
        AudioFileTypeRepository $audioFileTypeRepository
    ) {
        $this->entityManager = $entityManager;
        $this->songRepository = $songRepository;
        $this->audioFileRepository = $audioFileRepository;
        $this->projectRepository = $projectRepository;
        $this->validator = $validator;
        $this->tablatureRepository = $tablatureRepository;
        $this->lyricsRepository = $lyricsRepository;
        $this->audioFileTypeRepository = $audioFileTypeRepository;
    }


    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $songs = $this->songRepository->findAll();

        return $this->json($songs, JsonResponse::HTTP_OK, [], ['groups' => 'song']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $song = $this->songRepository->find($id);

        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $audioFiles = $this->audioFileRepository->findBy(['song' => $song]);
        $tablatures = $this->tablatureRepository->findBy(['song' => $song]);
        $lyrics = $this->lyricsRepository->findBy(['song' => $song]);
        $audioFileTypes = $this->audioFileTypeRepository->findAll();

        $data = [
            'song' => $song,
            'audioFiles' => $audioFiles,
            'tablatures' => $tablatures,
            'lyrics' => $lyrics,
            'audioFileTypes' => $audioFileTypes
        ];

        return $this->json($data, JsonResponse::HTTP_OK, [], ['groups' => ['song', 'audioFile', 'tablature', 'lyrics', 'audioFileType']]);
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

        if (!isset($data['project_id'])) {
            return $this->json(['error' => 'Project ID is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $project = $this->projectRepository->find($data['project_id']);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $song = new Song();
        $song->setTitle(trim($data['title']));
        $song->setProject($project);
        $song->setCreatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($song);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($song);
        $this->entityManager->flush();

        return $this->json($song, JsonResponse::HTTP_CREATED, [], ['groups' => 'song']);
    }


    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $song = $this->songRepository->find($id);

        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['title']) && empty(trim($data['title']))) {
            return $this->json(['error' => 'Title cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['project_id'])) {
            $project = $this->projectRepository->find($data['project_id']);
            if (!$project) {
                return $this->json(['error' => 'Project not found'], JsonResponse::HTTP_NOT_FOUND);
            }
            $song->setProject($project);
        }

        if (isset($data['bpm'])) {
            if (!is_numeric($data['bpm']) || $data['bpm'] < 20 || $data['bpm'] > 280) {
                return $this->json(['error' => 'BPM must be a number between 20 and 280'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $song->setBpm($data['bpm']);
        }

        if (isset($data['scale'])) {
            $song->setScale(trim($data['scale']));
        }

        if (isset($data['lyrics'])) {
            $lyrics = $this->lyricsRepository->findOneBy(['song' => $song]);
            if ($lyrics) {
                $lyrics->setContent($data['lyrics']);
                $lyrics->setUpdatedAt(new \DateTimeImmutable());
            } else {
                $newLyrics = new Lyrics();
                $newLyrics->setSong($song);
                $newLyrics->setContent($data['lyrics']);
                $newLyrics->setCreatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($newLyrics);
            }
        }

        $song->setTitle(isset($data['title']) ? trim($data['title']) : $song->getTitle());
        $song->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($song);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($song, JsonResponse::HTTP_OK, [], ['groups' => 'song']);
    }


    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $song = $this->songRepository->find($id);

        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($song);
        $this->entityManager->flush();

        return $this->json(['message' => 'Song deleted successfully'], JsonResponse::HTTP_OK);
    }
}
