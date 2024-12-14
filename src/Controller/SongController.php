<?php

namespace App\Controller;

use App\Entity\Song;
use App\Entity\Lyrics;
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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\NotificationService;

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
    private $secretStreaming;
    private $notificationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        SongRepository $songRepository,
        AudioFileRepository $audioFileRepository,
        ProjectRepository $projectRepository,
        ValidatorInterface $validator,
        TablatureRepository $tablatureRepository,
        LyricsRepository $lyricsRepository,
        AudioFileTypeRepository $audioFileTypeRepository,
        ParameterBagInterface $params,
        NotificationService $notificationService
    ) {
        $this->entityManager = $entityManager;
        $this->songRepository = $songRepository;
        $this->audioFileRepository = $audioFileRepository;
        $this->projectRepository = $projectRepository;
        $this->validator = $validator;
        $this->tablatureRepository = $tablatureRepository;
        $this->lyricsRepository = $lyricsRepository;
        $this->audioFileTypeRepository = $audioFileTypeRepository;
        $this->secretStreaming = $params->get("secret_streaming");
        $this->notificationService = $notificationService;
    }

    private function verifyProjectAccess($project, $currentUser): bool
    {
        if (!$currentUser || !$project->getMembers()->contains($currentUser)) {
            return false;
        }
        return true;
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $song = $this->songRepository->find($id);

        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();
        if (!$this->verifyProjectAccess($song->getProject(), $currentUser)) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $audioFiles = $this->audioFileRepository->findBy(['song' => $song]);
        $tablatures = $this->tablatureRepository->findBy(['song' => $song]);
        $lyrics = $this->lyricsRepository->findBy(['song' => $song]);
        $audioFileTypes = $this->audioFileTypeRepository->findAll();
        $audioData = [];

        foreach ($audioFiles as $audioFile) {
            $expiresAt = time() + 3600;
            $signature = hash_hmac('sha256', $audioFile->getId() . $expiresAt, $this->secretStreaming);
            $signedUrl = 'stream/' . $audioFile->getId() . '?expires=' . $expiresAt . '&signature=' . $signature;

            array_push($audioData, [
                'id' => $audioFile->getId(),
                'filename' => $audioFile->getFilename(),
                'path' => $audioFile->getPath(),
                'created_at' => $audioFile->getCreatedAt(),
                'updated_at' => $audioFile->getUpdatedAt(),
                'description' => $audioFile->getDescription(),
                'audioFileType' => [
                    'name' => $audioFile->getAudioFileType()->getName(),
                ],
                'signed_url' => $signedUrl,
            ]);
        }

        $data = [
            'song' => $song,
            'audioFiles' => $audioData,
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

        if (!isset($data['is_public'])) {
            return $this->json(['error' => 'Is public is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $project = $this->projectRepository->find($data['project_id']);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();
        if (!$this->verifyProjectAccess($project, $currentUser)) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $song = new Song();
        $song->setTitle(trim($data['title']));
        $song->setProject($project);
        $song->setCreatedAt(new \DateTimeImmutable());
        $song->setPublic($data['is_public']);

        $errors = $this->validator->validate($song);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($song);
        $this->entityManager->flush();

        $this->notificationService->notifyProjectMembers(
            sprintf(
                '%s added a new song "%s" to the project "%s"',
                $currentUser->getUsername(),
                $song->getTitle(),
                $project->getName()
            ),
            'song_created',
            sprintf(
                '/songs/%d',
                $song->getId()
            ),
            $project,
            [
                'songTitle' => $song->getTitle(),
                'projectName' => $project->getName(),
                'isPublic' => $song->isPublic()
            ]
        );

        return $this->json($song, JsonResponse::HTTP_CREATED, [], ['groups' => 'song']);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $song = $this->songRepository->find($id);

        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();
        if (!$this->verifyProjectAccess($song->getProject(), $currentUser)) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['title']) && empty(trim($data['title']))) {
            return $this->json(['error' => 'Title cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['title'])) {
            $song->setTitle($data['title']);
        }

        if (isset($data['is_public'])) {
            $song->setPublic($data['is_public']);
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

        $song->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($song);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        $updatedFields = array_intersect_key($data, array_flip(['title', 'is_public', 'bpm', 'scale', 'lyrics']));

        $this->notificationService->notifyProjectMembers(
            sprintf(
                '%s updated the song "%s"',
                $currentUser->getUsername(),
                $song->getTitle()
            ),
            'song_updated',
            sprintf(
                '/songs/%d',
                $song->getId()
            ),
            $song->getProject(),
            [
                'songTitle' => $song->getTitle(),
                'projectName' => $song->getProject()->getName(),
                'updatedFields' => array_keys($updatedFields)
            ]
        );

        return $this->json($song, JsonResponse::HTTP_OK, [], ['groups' => 'song']);
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $song = $this->songRepository->find($id);

        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();
        if (!$this->verifyProjectAccess($song->getProject(), $currentUser)) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }
        $lyrics = $this->lyricsRepository->findBy(['song' => $song]);
        foreach ($lyrics as $lyric) {
            $this->entityManager->remove($lyric);
        }

        $tablatures = $this->tablatureRepository->findBy(['song' => $song]);
        foreach ($tablatures as $tablature) {
            $this->entityManager->remove($tablature);
        }

        $this->entityManager->remove($song);
        $this->entityManager->flush();

        $project = $song->getProject();
        $this->notificationService->notifyProjectMembers(
            sprintf(
                '%s deleted the song "%s" from project "%s"',
                $currentUser->getUsername(),
                $song->getTitle(),
                $project->getName()
            ),
            'song_deleted',
            sprintf(
                '/projects/%d',
                $project->getId()
            ),
            $project,
            [
                'songTitle' => $song->getTitle(),
                'projectName' => $project->getName()
            ]
        );

        return $this->json(['message' => 'Song deleted successfully'], JsonResponse::HTTP_OK);
    }
}
