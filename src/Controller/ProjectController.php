<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Invitation;
use App\Repository\ProjectRepository;
use App\Repository\AudioFileRepository;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\NotificationService;

#[Route('/api/projects', name: 'project_')]
class ProjectController extends AbstractController
{
    private $entityManager;
    private $repository;
    private $validator;
    private $songRepository;
    private $userRepository;
    private $audioFileRepository;
    private $invitationRepository;
    private $secretStreaming;
    private $notificationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProjectRepository $repository,
        ValidatorInterface $validator,
        SongRepository $songRepository,
        ParameterBagInterface $params,
        UserRepository $userRepository,
        AudioFileRepository $audioFileRepository,
        InvitationRepository $invitationRepository,
        NotificationService $notificationService
    ) {
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->validator = $validator;
        $this->songRepository = $songRepository;
        $this->userRepository = $userRepository;
        $this->secretStreaming = $params->get("secret_streaming");
        $this->audioFileRepository = $audioFileRepository;
        $this->invitationRepository = $invitationRepository;
        $this->notificationService = $notificationService;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $projects = $this->repository->findByMember($currentUser);
        return $this->json($projects, JsonResponse::HTTP_OK, [], ['groups' => 'project']);
    }

    #[Route('/public', name: 'list_public_projects', methods: ['GET'])]
    public function getPublic(): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $publicProjects = $this->repository->findBy([
            'isPublic' => true
        ]);

        return $this->json($publicProjects, JsonResponse::HTTP_OK, [], ['groups' => 'project']);
    }

    #[Route('/{projectId<\d+>}/members/{memberId<\d+>}', name: 'remove_member', methods: ['DELETE'])]
    public function removeMember(int $projectId, int $memberId): JsonResponse
    {
        $project = $this->repository->find($projectId);
        $member = $this->userRepository->find($memberId);
        $currentUser = $this->getUser();

        if (!$project || !$project->getMembers()->contains($currentUser)) {
            return $this->json(['error' => 'Project not found or access denied'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (!$project || !$member) {
            return $this->json(['error' => 'Project or member not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (!$project->getMembers()->contains($member)) {
            return $this->json(['error' => 'Member not part of this project'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $this->entityManager->beginTransaction();
            $invitations = $this->invitationRepository->findBy([
                'project' => $project,
                'status' => ['accepted', 'pending'],
                'recipient' => $member
            ]);

            foreach ($invitations as $invitation) {
                $invitation->setStatus('revoked');
                $this->entityManager->persist($invitation);
            }

            $requests = $this->invitationRepository->findBy([
                'project' => $project,
                'status' => ['accepted', 'pending'],
                'sender' => $member,
                'type' => 'request'
            ]);

            foreach ($requests as $request) {
                $request->setStatus('revoked');
                $this->entityManager->persist($request);
            }

            $project->removeMember($member);
            $this->entityManager->flush();
            $this->notificationService->notifyProjectMembers(
                sprintf(
                    '%s removed %s from the project "%s"',
                    $currentUser->getUsername(),
                    $member->getUsername(),
                    $project->getName()
                ),
                'project_member_removed',
                sprintf(
                    '/projects/%d',
                    $project->getId()
                ),
                $project,
                [
                    'projectName' => $project->getName(),
                    'removedMemberName' => $member->getUsername()
                ]
            );
            $this->notificationService->createSingleNotification(
                $member,
                sprintf(
                    'You have been removed from the project "%s"',
                    $project->getName()
                ),
                'project_removal_notification',
                '/projects',
                $project,
                ['projectName' => $project->getName()]
            );

            return $this->json(['message' => 'Member removed successfully'], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return $this->json(
                ['error' => 'Could not remove member', 'details' => $e->getMessage()],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $project = $this->repository->find($id);
        $isOwner = $project->getMembers()->contains($currentUser);

        if (!$project || !$isOwner && !$project->isPublic()) {
            return $this->json(['error' => 'Project not found or access denied'], JsonResponse::HTTP_NOT_FOUND);
        }

        $songs = $this->songRepository->findBy(['project' => $project]);
        $songsData = [];

        foreach ($songs as $song) {
            $audioFiles = $this->audioFileRepository->findBy(['song' => $song]);
            $masterFiles = array_filter($audioFiles, function ($file) {
                return $file->getAudioFileType() && $file->getAudioFileType()->getName() === 'Master';
            });
            $latestMasterFile = null;
            if (!empty($masterFiles)) {
                usort($masterFiles, function ($a, $b) {
                    return $b->getCreatedAt() <=> $a->getCreatedAt();
                });
                $latestMasterFile = $masterFiles[0];
            }

            $audioData = [];
            if ($latestMasterFile) {
                $expiresAt = time() + 3600;
                $signature = hash_hmac('sha256', $latestMasterFile->getId() . $expiresAt, $this->secretStreaming);
                $signedUrl = 'stream/' . $latestMasterFile->getId() . '?expires=' . $expiresAt . '&signature=' . $signature;

                $audioData = [
                    'id' => $latestMasterFile->getId(),
                    'filename' => $latestMasterFile->getFilename(),
                    'path' => $latestMasterFile->getPath(),
                    'created_at' => $latestMasterFile->getCreatedAt(),
                    'updated_at' => $latestMasterFile->getUpdatedAt(),
                    'description' => $latestMasterFile->getDescription(),
                    'audioFileType' => [
                        'name' => $latestMasterFile->getAudioFileType()->getName(),
                    ],
                    'signed_url' => $signedUrl,
                ];
            }
            if ($isOwner) {
                $songsData[] = [
                    'id' => $song->getId(),
                    'title' => $song->getTitle(),
                    'isPublic' => $song->isPublic(),
                    'created_at' => $song->getCreatedAt(),
                    'updated_at' => $song->getUpdatedAt(),
                    'audioFiles' => $audioData ? [$audioData] : [],
                    'bpm' => $song->getBpm(),
                    'scale' => $song->getScale(),
                ];
            } else {
                if ($song->isPublic()) {
                    $songsData[] = [
                        'id' => $song->getId(),
                        'title' => $song->getTitle(),
                        'audioFiles' => $audioData ? [$audioData] : [],
                    ];
                }
            }
        }
        $members = [];
        if ($isOwner) {
            $members = $project->getMembers()->map(function ($member) {
                return [
                    'id' => $member->getId(),
                    'username' => $member->getUsername(),
                    'email' => $member->getEmail(),
                    'isPublic' => $member->isPublic(),
                ];
            })->toArray();
        } else {
            $members = $project->getMembers()->filter(function ($member) {
                return $member->isPublic();
            })->map(function ($member) {
                return [
                    'id' => $member->getId(),
                    'username' => $member->getUsername(),
                    'email' => $member->getEmail(),
                    'isPublic' => $member->isPublic(),
                ];
            })->toArray();
        }

        return $this->json(
            [
                'project' => [
                    'id' => $project->getId(),
                    'isPublic' => $project->isPublic(),
                    'name' => $project->getName(),
                    'description' => $project->getDescription(),
                    'profileImage' => $project->getProfileImage(),
                    'members' => $project->getMembers()->map(function ($member) {
                        return [
                            'id' => $member->getId(),
                            'username' => $member->getUsername(),
                            'email' => $member->getEmail(),
                            'isPublic' => $member->isPublic(),
                        ];
                    })->toArray(),
                ],
                'songs' => $songsData,
            ],
            JsonResponse::HTTP_OK
        );
    }


    #[Route('/update-profile-image', name: 'update_profile_image', methods: ['POST'])]
    public function uploadProfileImage(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $projectId = $request->get('project_id');
        if (!$projectId) {
            return $this->json(['error' => 'Missing project_id'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $project = $this->repository->find($projectId);
        if (!$project || !$project->getMembers()->contains($currentUser)) {
            return $this->json(['error' => 'Project not found or access denied'], JsonResponse::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('profile_image');
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/gif'])) {
            return $this->json(['error' => 'Invalid file type. Allowed types: jpeg, png, gif'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $uploadDirectory = $this->getParameter('project_images_directory');
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = preg_replace('/[^a-zA-Z0-9-_]/', '_', $originalFilename);

        do {
            $filename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
            $targetFilePath = $uploadDirectory . '/' . $filename;
        } while (file_exists($targetFilePath));

        try {
            $file->move($uploadDirectory, $filename);
            $project->setProfileImage($filename);
            $this->entityManager->flush();

            $this->notificationService->notifyProjectMembers(
                sprintf(
                    '%s updated the project image of "%s"',
                    $currentUser->getUsername(),
                    $project->getName()
                ),
                'project_image_updated',
                sprintf(
                    '/projects/%d',
                    $project->getId()
                ),
                $project,
                ['projectName' => $project->getName()]
            );

            return $this->json(['message' => 'Image uploaded successfully'], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'File upload failed', 'details' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['name']) || empty(trim($data['name']))) {
            return $this->json(['error' => 'Name is required and cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $existingProject = $this->repository->findOneBy(['name' => trim($data['name'])]);
        if ($existingProject) {
            return $this->json(['error' => 'A project with this name already exists'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['isPublic'])) {
            return $this->json(['error' => 'Is Public is required'], JsonResponse::HTTP_BAD_REQUEST);
        }


        if (isset($data['description']) && strlen($data['description']) > 2000) {
            return $this->json(['error' => 'Description must not exceed 2000 characters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $project = new Project();
        $project->setName(trim($data['name']));
        $project->setPublic($data['isPublic']);
        $project->setDescription(isset($data['description']) ? trim($data['description']) : null);

        $project->addMember($currentUser);

        $errors = $this->validator->validate($project);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $this->json($project, JsonResponse::HTTP_CREATED, [], ['groups' => 'project']);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $project = $this->repository->find($id);
        if (!$project || !$project->getMembers()->contains($currentUser)) {
            return $this->json(['error' => 'Project not found or access denied'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['name']) && empty(trim($data['name']))) {
            return $this->json(['error' => 'Name cannot be empty'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['name']) && !empty(trim($data['name']))) {
            $existingProject = $this->repository->findOneBy(['name' => trim($data['name'])]);
            if ($existingProject && $existingProject->getId() !== $project->getId()) {
                return $this->json(['error' => 'A project with this name already exists'], JsonResponse::HTTP_BAD_REQUEST);
            }
        }

        if (isset($data['description']) && strlen($data['description']) > 2000) {
            return $this->json(['error' => 'Description must not exceed 2000 characters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $project->setName(isset($data['name']) ? trim($data['name']) : $project->getName());
        $project->setDescription(isset($data['description']) ? trim($data['description']) : $project->getDescription());
        $project->setPublic($data["isPublic"]);

        $errors = $this->validator->validate($project);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        $this->notificationService->notifyProjectMembers(
            sprintf(
                '%s updated the project "%s"',
                $currentUser->getUsername(),
                $project->getName()
            ),
            'project_updated',
            sprintf(
                '/projects/%d',
                $project->getId()
            ),
            $project,
            [
                'projectName' => $project->getName(),
                'updatedFields' => array_keys($data)
            ]
        );

        return $this->json($project, JsonResponse::HTTP_OK, [], ['groups' => 'project']);
    }


    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $project = $this->repository->find($id);
        if (!$project || !$project->getMembers()->contains($currentUser)) {
            return $this->json(['error' => 'Project not found or access denied'], JsonResponse::HTTP_NOT_FOUND);
        }

        try {
            $this->entityManager->beginTransaction();
            $invitations = $this->entityManager
                ->getRepository(Invitation::class)
                ->findBy(['project' => $project]);

            foreach ($invitations as $invitation) {
                $this->entityManager->remove($invitation);
            }
            $this->entityManager->remove($project);
            $this->entityManager->flush();

            $this->entityManager->beginTransaction();

            $this->notificationService->notifyProjectMembers(
                sprintf(
                    '%s deleted the project "%s"',
                    $currentUser->getUsername(),
                    $project->getName()
                ),
                'project_deleted',
                '/projects',
                $project,
                ['projectName' => $project->getName()]
            );

            return $this->json(['message' => 'Project deleted successfully'], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            return $this->json(
                ['error' => 'Could not delete project', 'details' => $e->getMessage()],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
