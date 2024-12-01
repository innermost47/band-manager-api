<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\AudioFileRepository;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;

class FileController extends AbstractController
{
    private $privateFilesystem;
    private $uploadDir;
    private $audioFileRepository;

    public function __construct(ParameterBagInterface $params, Filesystem $privateFilesystem, AudioFileRepository $audioFileRepository,)
    {
        $this->privateFilesystem = $privateFilesystem;
        $this->uploadDir = realpath($params->get('kernel.project_dir'))  . '/var/uploads/private/';
        $this->audioFileRepository = $audioFileRepository;
    }

    #[Route("/audio/{filename}", name: 'audio_private', methods: ["GET"])]
    public function serveAudio(string $filename): Response
    {
        if (!$this->getUser()) {
            throw new AccessDeniedException("You must be logged in to access this file.");
        }
        $filePath = $this->uploadDir . "audio/" . $filename;
        $normalizedFilePath = realpath($filePath);
        if ($normalizedFilePath === false || !$this->privateFilesystem->exists($normalizedFilePath)) {
            throw $this->createNotFoundException("The requested file does not exist.");
        }
        return new BinaryFileResponse($filePath);
    }

    #[Route("/profile-image/{filename}", name: 'profile_image_private', methods: ["GET"])]
    public function serveProfileImage(string $filename): Response
    {
        if (!$this->getUser()) {
            throw new AccessDeniedException("You must be logged in to access this file.");
        }
        $filePath = $this->uploadDir . "project_images/" . $filename;
        $normalizedFilePath = realpath($filePath);
        if ($normalizedFilePath === false || !$this->privateFilesystem->exists($normalizedFilePath)) {
            throw $this->createNotFoundException("The requested file does not exist.");
        }
        return new BinaryFileResponse($filePath);
    }

    #[Route('/audio/download/{id}', name: 'download_audio', methods: ['GET'])]
    public function downloadAudio(int $id): Response
    {
        $audioFile = $this->audioFileRepository->find($id);

        if (!$audioFile) {
            return $this->json(['error' => 'File not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $filePath = $this->uploadDir . $audioFile->getPath();

        if (!file_exists($filePath)) {
            return $this->json(['error' => 'File does not exist on the server'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($filePath);
    }
}
