<?php

namespace App\Controller;

use App\Entity\AudioFile;
use App\Repository\AudioFileRepository;
use App\Repository\AudioFileTypeRepository;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/api/audio-files', name: 'audio_file_')]
class AudioFileController extends AbstractController
{
    private $entityManager;
    private $audioFileRepository;
    private $songRepository;
    private $validator;
    private $audioFileTypeRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        AudioFileRepository $audioFileRepository,
        SongRepository $songRepository,
        ValidatorInterface $validator,
        AudioFileTypeRepository $audioFileTypeRepository
    ) {
        $this->entityManager = $entityManager;
        $this->audioFileRepository = $audioFileRepository;
        $this->songRepository = $songRepository;
        $this->validator = $validator;
        $this->audioFileTypeRepository = $audioFileTypeRepository;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $audioFiles = $this->audioFileRepository->findAll();
        return $this->json($audioFiles, JsonResponse::HTTP_OK, [], ['groups' => 'audio_file']);
    }

    #[Route('/by-song/{songId}', name: 'list_by_song', methods: ['GET'])]
    public function listBySong(int $songId): JsonResponse
    {
        $song = $this->songRepository->find($songId);

        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $audioFiles = $this->audioFileRepository->findBy(['song_id' => $songId]);

        if (empty($audioFiles)) {
            return $this->json(['error' => 'No audio files found for this song'], JsonResponse::HTTP_NOT_FOUND);
        }

        $fileMetadata = [];
        foreach ($audioFiles as $file) {
            $fileMetadata[] = [
                'id' => $file->getId(),
                'filename' => $file->getFilename(),
                'path' => $file->getPath(),
                'created_at' => $file->getCreatedAt(),
            ];
        }

        return $this->json($fileMetadata, JsonResponse::HTTP_OK);
    }

    #[Route('/by-song/{songId}/download/{fileId}', name: 'download_by_song', methods: ['GET'])]
    public function downloadBySong(int $songId, int $fileId): BinaryFileResponse
    {
        $song = $this->songRepository->find($songId);

        if (!$song) {
            throw $this->createNotFoundException('Song not found');
        }

        $audioFile = $this->audioFileRepository->find($fileId);

        if (!$audioFile || $audioFile->getSongId()->getId() !== $songId) {
            throw $this->createNotFoundException('Audio file not found or does not belong to the specified song');
        }

        $filePath = $audioFile->getPath();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Audio file not found on the server');
        }

        return new BinaryFileResponse($filePath);
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $songId = $request->get('song_id');
        $audioFileTypeId = $request->get('audio_file_type_id');
        $files = $request->files->get('audioFiles');
        if (!$songId || !$files || !$audioFileTypeId) {
            return $this->json(['error' => 'Missing song_id, audio_file_type_id or audio files'], JsonResponse::HTTP_BAD_REQUEST);
        }
        $song = $this->songRepository->find($songId);
        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }
        $audioFileType = $this->audioFileTypeRepository->find($audioFileTypeId);
        if (!$audioFileType) {
            return $this->json(['error' => 'Audio file type not found'], JsonResponse::HTTP_NOT_FOUND);
        }
        $uploadedFiles = [];
        $uploadDirectory = $this->getParameter('audio_upload_directory');
        foreach ($files as $file) {
            if (!($file instanceof UploadedFile)) {
                return $this->json(['error' => 'Invalid file instance'], JsonResponse::HTTP_BAD_REQUEST);
            }
            if (!$file->isValid()) {
                return $this->json(['error' => 'One or more files are invalid'], JsonResponse::HTTP_BAD_REQUEST);
            }
            if (!in_array($file->getMimeType(), ['audio/mpeg', 'audio/wav', 'audio/ogg'])) {
                return $this->json(['error' => 'Invalid file type. Allowed types: mp3, wav, ogg'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = preg_replace('/[^a-zA-Z0-9-_]/', '_', $originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
            try {
                $file->move($uploadDirectory, $newFilename);
            } catch (FileException $e) {
                return $this->json(['error' => 'Failed to upload file'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }
            $relativePath = str_replace($this->getParameter('kernel.project_dir') . '/public/', '', $uploadDirectory . '/' . $newFilename);
            $audioFile = new AudioFile();
            $audioFile->setSong($song);
            $audioFile->setFilename($newFilename);
            $audioFile->setPath($relativePath);
            $audioFile->setAudioFileType($audioFileType);
            $audioFile->setCreatedAt(new \DateTimeImmutable());
            $errors = $this->validator->validate($audioFile);
            if (count($errors) > 0) {
                return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
            }
            $this->entityManager->persist($audioFile);
            $uploadedFiles[] = $audioFile;
        }
        $this->entityManager->flush();
        return $this->json([
            'message' => 'Files uploaded successfully',
            'files' => $uploadedFiles
        ], JsonResponse::HTTP_CREATED, [], ['groups' => 'audio_file']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $audioFile = $this->audioFileRepository->find($id);

        if (!$audioFile) {
            return $this->json(['error' => 'Audio file not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['description'])) {
            $audioFile->setDescription($data['description']);
        }
        if (isset($data['audio_file_type_id'])) {
            $audioFileType = $this->audioFileTypeRepository->find($data['audio_file_type_id']);
            if (!$audioFileType) {
                return $this->json(['error' => 'Audio file type not found'], JsonResponse::HTTP_NOT_FOUND);
            }
            $audioFile->setAudioFileType($audioFileType);
        }

        $errors = $this->validator->validate($audioFile);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($audioFile, JsonResponse::HTTP_OK, [], ['groups' => 'audio_file']);
    }


    #[Route('/download/{id}', name: 'download', methods: ['GET'])]
    public function download(int $id): Response
    {
        $audioFile = $this->audioFileRepository->find($id);

        if (!$audioFile) {
            return $this->json(['error' => 'File not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $filePath = $audioFile->getPath();

        if (!file_exists($filePath)) {
            return $this->json(['error' => 'File does not exist on the server'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($filePath);
    }


    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $audioFile = $this->audioFileRepository->find($id);

        if (!$audioFile) {
            return $this->json(['error' => 'File not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $filePath = $audioFile->getPath();

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($audioFile);
        $this->entityManager->flush();

        return $this->json(['message' => 'File deleted successfully'], JsonResponse::HTTP_OK);
    }
}
