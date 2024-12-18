<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\AudioFileRepository;
use App\Repository\ProjectRepository;
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
    private $projectRepository;

    public function __construct(ParameterBagInterface $params, Filesystem $privateFilesystem, AudioFileRepository $audioFileRepository, ProjectRepository $projectRepository)
    {
        $this->privateFilesystem = $privateFilesystem;
        $this->uploadDir = realpath($params->get('kernel.project_dir'))  . '/var/uploads/private/';
        $this->audioFileRepository = $audioFileRepository;
        $this->projectRepository  = $projectRepository;
    }

    private function verifyProjectAccess($project): void
    {
        $currentUser = $this->getUser();
        if (!$currentUser || !$project->getMembers()->contains($currentUser)) {
            throw $this->createAccessDeniedException('Access denied to this project.');
        }
    }

    #[Route("/audio/{filename}", name: 'audio_private', methods: ["GET"])]
    public function serveAudio(string $filename): Response
    {
        $audioFile = $this->audioFileRepository->findOneBy(['path' => "audio/" . $filename]);

        if (!$audioFile) {
            throw $this->createNotFoundException("The requested file does not exist.");
        }

        $song = $audioFile->getSong();
        if (!$song) {
            throw $this->createNotFoundException("The song associated with this file does not exist.");
        }

        $this->verifyProjectAccess($song->getProject());

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
        $currentUser = $this->getUser();
        if (!$currentUser) {
            throw new AccessDeniedException("You must be logged in to access this file.");
        }

        $project = $this->projectRepository->findOneBy(['profileImage' => $filename]);

        if (!$project) {
            throw $this->createNotFoundException("No project associated with this profile image.");
        }

        $this->verifyProjectAccess($project);

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
        $song = $audioFile->getSong();
        if (!$song) {
            return $this->json(['error' => 'The song associated with this file does not exist.'], JsonResponse::HTTP_NOT_FOUND);
        }
        $this->verifyProjectAccess($song->getProject());
        $filePath = $this->uploadDir . $audioFile->getPath();

        if (!file_exists($filePath)) {
            return $this->json(['error' => 'File does not exist on the server'], JsonResponse::HTTP_NOT_FOUND);
        }
        return new BinaryFileResponse($filePath);
    }

    #[Route("/document/{filename}/{projectId}", name: 'pdf_private', methods: ["GET"])]
    public function serveDocument(string $filename, int $projectId): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            throw new AccessDeniedException("You must be logged in to access this file.");
        }

        $project = $this->projectRepository->find($projectId);

        if (!$project) {
            throw $this->createNotFoundException("No project associated with this profile image.");
        }

        $this->verifyProjectAccess($project);

        $filePath = $this->uploadDir . "documents/" . $filename;
        $normalizedFilePath = realpath($filePath);

        if ($normalizedFilePath === false || !$this->privateFilesystem->exists($normalizedFilePath)) {
            throw $this->createNotFoundException("The requested file does not exist.");
        }

        return new BinaryFileResponse($filePath);
    }
}
