<?php

namespace App\Controller;

use App\Service\DocumentService;
use App\Service\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ProjectRepository;
use App\Repository\DocumentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/documents')]
class DocumentController extends AbstractController
{
    private $documentService;
    private $repository;
    private $projectRepository;
    private $projectService;
    private $notificationService;
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager, DocumentService $documentService, DocumentRepository $repository, ProjectRepository $projectRepository, ProjectService $projectService, NotificationService $notificationService)
    {
        $this->documentService = $documentService;
        $this->repository = $repository;
        $this->projectRepository = $projectRepository;
        $this->projectService = $projectService;
        $this->notificationService = $notificationService;
        $this->entityManager = $entityManager;
    }


    #[Route('/project/{id}', name: 'get_project_documents', methods: ['GET'])]
    public function getProjectDocuments(int $id): Response
    {
        $project = $this->projectRepository->find($id);
        $this->projectService->verifyProjectAccess($project, $this->getUser());
        $documents = $this->documentService->getProjectDocuments($id);
        return $this->json($documents);
    }

    #[Route('/upload', name: 'upload_document', methods: ['POST'])]
    public function uploadDocument(Request $request): Response
    {
        $file = $request->files->get('file');
        $projectId = $request->request->get('project_id');
        $project = $this->projectRepository->find($projectId);

        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        $this->projectService->verifyProjectAccess($project, $this->getUser());

        try {
            $document = $this->documentService->uploadDocument($file, $projectId, $this->getUser());
            return $this->json($document, 200, [], ['groups' => ['document:read']]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}', name: 'delete_document', methods: ['DELETE'])]
    public function deleteDocument(int $id): Response
    {
        $document = $this->repository->find($id);
        if (!$document) {
            throw $this->createNotFoundException('Document not found');
        }

        $this->projectService->verifyProjectAccess($document->getProject(), $this->getUser());

        try {
            $filePath = $this->getParameter('kernel.project_dir') . '/var/uploads/private/' . $document->getFilepath();
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $project = $document->getProject();
            $filename = $document->getFilename();

            $this->entityManager->remove($document);
            $this->entityManager->flush();

            $this->notificationService->notifyProjectMembers(
                sprintf(
                    '%s deleted the document "%s"',
                    $this->getUser()->getUsername(),
                    $filename
                ),
                'document_deleted',
                sprintf(
                    '/projects/%d/documents',
                    $project->getId()
                ),
                $project,
                [
                    'projectName' => $project->getName(),
                    'documentName' => $filename,
                    'deletedBy' => $this->getUser()->getUsername()
                ]
            );

            return $this->json(
                ['message' => 'Document deleted successfully'],
                JsonResponse::HTTP_OK
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'An error occurred while deleting the document'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
