<?php

namespace App\Controller;

use App\Entity\AudioFile;
use App\Repository\AudioFileRepository;
use App\Repository\AudioFileTypeRepository;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

#[Route('', name: 'audio_file_')]
class AudioFileController extends AbstractController
{
    private $entityManager;
    private $audioFileRepository;
    private $songRepository;
    private $validator;
    private $audioFileTypeRepository;
    private $uploadDir;
    private $secretStreaming;

    public function __construct(
        EntityManagerInterface $entityManager,
        AudioFileRepository $audioFileRepository,
        SongRepository $songRepository,
        ValidatorInterface $validator,
        AudioFileTypeRepository $audioFileTypeRepository,
        ParameterBagInterface $params
    ) {
        $this->entityManager = $entityManager;
        $this->audioFileRepository = $audioFileRepository;
        $this->songRepository = $songRepository;
        $this->validator = $validator;
        $this->audioFileTypeRepository = $audioFileTypeRepository;
        $this->uploadDir = realpath($params->get('kernel.project_dir'))  . '/var/uploads/private/';
        $this->secretStreaming = $params->get("secret_streaming");
    }

    private function verifyProjectAccess($project, $currentUser): bool
    {
        if (!$currentUser || !$project->getMembers()->contains($currentUser)) {
            return false;
        }
        return true;
    }

    #[Route('/api/audio-files/by-song/{songId}', name: 'list_by_song', methods: ['GET'])]
    public function listBySong(int $songId): JsonResponse
    {
        $song = $this->songRepository->find($songId);

        if (!$song) {
            return $this->json(['error' => 'Song not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();
        if (!$this->verifyProjectAccess($song->getProject(), $currentUser)) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $audioFiles = $this->audioFileRepository->findBy(['song_id' => $songId]);

        if (empty($audioFiles)) {
            return $this->json(['error' => 'No audio files found for this song'], JsonResponse::HTTP_NOT_FOUND);
        }

        $fileMetadata = [];
        foreach ($audioFiles as $file) {
            $expiresAt = time() + 3600;
            $signature = hash_hmac('sha256', $file->getId() . $expiresAt, $this->secretStreaming);
            $signedUrl = 'stream-audio/' . $file->getId() . '?expires=' . $expiresAt . '&signature=' . $signature;

            $fileMetadata[] = [
                'id' => $file->getId(),
                'filename' => $file->getFilename(),
                'path' => $file->getPath(),
                'created_at' => $file->getCreatedAt(),
                'signed_url' => $signedUrl,
            ];
        }

        return $this->json($fileMetadata, JsonResponse::HTTP_OK);
    }


    #[Route('/stream/{fileId}', name: 'stream_audio', methods: ['GET'])]
    public function streamAudio(int $fileId, Request $request): Response
    {

        $expires = $request->query->get('expires');
        $signature = $request->query->get('signature');

        if (!$expires || !$signature || time() > $expires) {
            return $this->json(['error' => 'Link expired or invalid'], JsonResponse::HTTP_FORBIDDEN);
        }

        $expectedSignature = hash_hmac('sha256', $fileId . $expires, $this->secretStreaming);
        if (!hash_equals($expectedSignature, $signature)) {
            return $this->json(['error' => 'Invalid signature'], JsonResponse::HTTP_FORBIDDEN);
        }

        $file = $this->audioFileRepository->find($fileId);

        if (!$file) {
            return $this->json(['error' => 'File not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $filePath = $this->uploadDir . '/' . $file->getPath();
        if (!file_exists($filePath)) {
            return $this->json(['error' => 'File not found on server'], JsonResponse::HTTP_NOT_FOUND);
        }

        $fileSize = filesize($filePath);
        $range = $request->headers->get('Range');

        if ($range) {
            list(, $range) = explode('=', $range, 2);
            list($start, $end) = explode('-', $range, 2);

            $start = (int) $start;
            $end = $end === '' ? $fileSize - 1 : (int) $end;
            $length = $end - $start + 1;

            $response = new StreamedResponse(function () use ($filePath, $start, $length) {
                $stream = fopen($filePath, 'rb');
                fseek($stream, $start);
                echo fread($stream, $length);
                fclose($stream);
            });

            $response->headers->set('Content-Type', 'audio/mpeg');
            $response->headers->set('Content-Range', "bytes $start-$end/$fileSize");
            $response->headers->set('Content-Length', $length);
            $response->headers->set('Accept-Ranges', 'bytes');
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Expires', '0');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Range');
            $response->setStatusCode(Response::HTTP_PARTIAL_CONTENT);

            return $response;
        }

        $response = new BinaryFileResponse($filePath);
        $response->setAutoEtag();
        $response->headers->set('Content-Type', 'audio/mpeg');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $file->getFilename()
        );

        return $response;
    }

    #[Route('/api/audio-files/by-song/{songId}/download/{fileId}', name: 'download_by_song', methods: ['GET'])]
    public function downloadBySong(int $songId, int $fileId): BinaryFileResponse
    {
        $song = $this->songRepository->find($songId);

        if (!$song) {
            throw $this->createNotFoundException('Song not found');
        }

        $currentUser = $this->getUser();
        if (!$this->verifyProjectAccess($song->getProject(), $currentUser)) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
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

    #[Route('/api/audio-files/upload', name: 'upload', methods: ['POST'])]
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
        $currentUser = $this->getUser();
        if (!$this->verifyProjectAccess($song->getProject(), $currentUser)) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
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
            do {
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                $targetFilePath = $uploadDirectory . '/' . $newFilename;
            } while (file_exists($targetFilePath));
            try {
                $file->move($uploadDirectory, $newFilename);
            } catch (FileException $e) {
                return $this->json(['error' => 'Failed to upload file'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }
            $relativePath = str_replace($this->getParameter('kernel.project_dir') . '/var/uploads/private/', '', $uploadDirectory . '/' . $newFilename);
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

    #[Route('/api/audio-files/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $audioFile = $this->audioFileRepository->find($id);

        if (!$audioFile) {
            return $this->json(['error' => 'Audio file not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $song = $audioFile->getSong();

        $currentUser = $this->getUser();
        if (!$this->verifyProjectAccess($song->getProject(), $currentUser)) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
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


    #[Route('/api/audio-files/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $audioFile = $this->audioFileRepository->find($id);

        if (!$audioFile) {
            return $this->json(['error' => 'File not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $song = $audioFile->getSong();

        $currentUser = $this->getUser();
        if (!$this->verifyProjectAccess($song->getProject(), $currentUser)) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/var/uploads/private/' . $audioFile->getPath();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($audioFile);
        $this->entityManager->flush();

        return $this->json(['message' => 'File deleted successfully'], JsonResponse::HTTP_OK);
    }
}
