<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\NotificationService;
use App\Repository\ProjectRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class DocumentService
{
    private $em;
    private $projectRepository;
    private $notificationService;
    private $params;
    private $uploadDir;
    private $allowedMimeTypes;
    private $slugger;

    public function __construct(
        EntityManagerInterface $em,
        ProjectRepository $projectRepository,
        NotificationService $notificationService,
        ParameterBagInterface $params,
        SluggerInterface $slugger
    ) {
        $this->em = $em;
        $this->projectRepository = $projectRepository;
        $this->notificationService = $notificationService;
        $this->params = $params;
        $this->slugger = $slugger;
        $this->uploadDir = realpath($params->get('kernel.project_dir'))  . '/var/uploads/private/';
        $this->allowedMimeTypes = [
            'application/pdf',
            'application/zip',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
        ];
    }

    public function getProjectDocuments(int $projectId): array
    {
        $documents = $this->em->getRepository(Document::class)
            ->findBy(['project' => $projectId], ['id' => 'DESC']);

        return array_map(function ($document) {
            return [
                'id' => $document->getId(),
                'filename' => $document->getFilename(),
                'uploadedAt' => $document->getUploadedAt()->format('c'),
                'type' => $document->getType()
            ];
        }, $documents);
    }

    public function uploadDocument(UploadedFile $file, int $projectId, User $user): Document
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('The file is invalid');
        }
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('File type is not allowed');
        }

        $project = $this->projectRepository->find($projectId);
        if (!$project) {
            throw new \InvalidArgumentException('Project not found');
        }

        $uploadDirectory = $this->params->get('documents_upload_directory');

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $file->getClientOriginalExtension();

        do {
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;
            $targetFilePath = $uploadDirectory . '/' . $newFilename;
        } while (file_exists($targetFilePath));

        try {
            $file->move($uploadDirectory, $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Failed to upload file');
        }

        $document = new Document();
        $document
            ->setProject($project)
            ->setFilename($newFilename)
            ->setFilepath(str_replace($this->uploadDir, '', $targetFilePath))
            ->setUploadedAt(new \DateTimeImmutable())
            ->setType($mimeType);

        $this->em->persist($document);
        $this->em->flush();

        $this->notificationService->notifyProjectMembers(
            sprintf(
                '%s added a new document "%s"',
                $user->getUsername(),
                $document->getFilename()
            ),
            'document_uploaded',
            sprintf('/projects/%d', $project->getId()),
            $project,
            [
                'documentName' => $document->getFilename(),
                'projectName' => $project->getName()
            ]
        );

        return $document;
    }
}
