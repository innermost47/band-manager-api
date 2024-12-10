<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ProjectRepository;
use App\Repository\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\EmailService;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('', name: 'user_')]
class UserController extends AbstractController
{
    private $entityManager;
    private $userRepository;
    private $passwordHasher;
    private $validator;
    private $serializer;
    private $invitationRepository;
    private $projectRepository;
    private $emailService;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        ParameterBagInterface $params,
        SerializerInterface $serializer,
        InvitationRepository $invitationRepository,
        ProjectRepository $projectRepository
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->validator = $validator;
        $this->serializer = $serializer;
        $this->invitationRepository = $invitationRepository;
        $this->projectRepository = $projectRepository;
        $this->emailService = new EmailService($params);
    }

    #[Route('/signup', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json(['error' => 'Email already exists'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $plainPassword = $data['password'];
        if (
            strlen($plainPassword) < 8 ||
            !preg_match('/[A-Z]/', $plainPassword) ||
            !preg_match('/[a-z]/', $plainPassword) ||
            !preg_match('/[0-9]/', $plainPassword) ||
            !preg_match('/[\W]/', $plainPassword)
        ) {
            return $this->json([
                'error' => 'Password must be at least 8 characters long and include uppercase, lowercase, numeric, and special characters.'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $confirmPassword = $data['confirmPassword'];
        if ($plainPassword !== $confirmPassword) {
            return $this->json(['error' => 'Password mismatch'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $verificationCode = random_int(100000, 999999);

        $user = new User();
        $user->setUsername($data['name'] ?? $data['email']);
        $user->setEmail($data['email']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setRoles($data['roles'] ?? ['ROLE_USER']);
        $user->setVerificationCode($verificationCode);
        $user->setVerified(false);

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $recipientEmail = $user->getEmail();
        $subject = "Verify Your Email";
        $fromSubject = 'Account Verification';
        $body = "Thank you for signing up.\n\n" .
            "Please use the following code to verify your account:\n\n" .
            "Verification Code: $verificationCode\n\n" .
            "If you did not sign up, please ignore this email.";
        $altBody = $body;
        $isEmailSent = $this->emailService->sendEmail($recipientEmail, $subject, $body,  $altBody, $fromSubject);
        if ($isEmailSent) {
            return new JsonResponse(
                ['message' => 'User created successfully. Verification code sent to email.'],
                JsonResponse::HTTP_CREATED
            );
        } else {
            return new JsonResponse(
                ['message' => 'User created successfully, but email sending failed.'],
                JsonResponse::HTTP_CREATED
            );
        }
    }

    #[Route('/verify-email', name: 'verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'], $data['code'])) {
            return $this->json(['error' => 'Invalid data. Email and verification code are required.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $email = $data['email'];
        $verificationCode = $data['code'];

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['error' => 'User not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($user->isVerified()) {
            return $this->json(['message' => 'User is already verified.'], JsonResponse::HTTP_OK);
        }

        if ($user->getVerificationCode() != $verificationCode) {
            return $this->json(['error' => 'Invalid verification code.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user->setVerified(true);
        $user->setVerificationCode(null);
        $this->entityManager->flush();

        return $this->json(['message' => 'Email verified successfully.'], JsonResponse::HTTP_OK);
    }

    #[Route('/api/users/profile', name: 'get_profile', methods: ['GET'])]
    public function getProfile(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
            }
            $userData = $this->serializer->normalize($user, null, ['groups' => ['user']]);
            $sentInvitations = $user->getInvitations();
            $receivedInvitations = $this->invitationRepository->findBy(['recipient' => $user]);
            $userData['sent_invitations'] = $this->serializer->normalize($sentInvitations, null, ['groups' => ['user']]);
            $userData['received_invitations'] = $this->serializer->normalize($receivedInvitations, null, ['groups' => ['user']]);
            return $this->json($userData);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Unexpected error occurred', 'details' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getPublicUserData($user, $currentUser)
    {
        $responseData = [];
        $responseData["id"] = $user->getId();
        $responseData["username"] = $user->getUsername();
        if ($user->isEmailPublic()) {
            $responseData['email'] = $user->getEmail();
        }
        if ($user->isAddressPublic()) {
            $responseData['address'] = $user->getAddress();
        }
        if ($user->isPhonePublic()) {
            $responseData['phone'] = $user->getPhone();
        }
        if ($user->isSacemNumberPublic()) {
            $responseData['sacemNumber'] = $user->getSacemNumber();
        }
        if ($user->isBioPublic()) {
            $responseData['bio'] = $user->getBio();
        }
        if ($user->isProjectsPublic()) {
            $projects = $user->getProjects()->toArray();
            $publicProjects = array_filter($projects, function ($project) {
                return $project->isPublic();
            });
            $normalizedProjects = $this->serializer->normalize($publicProjects, null, ['groups' => ['user']]);
            $projectsWithStatus = array_map(function ($project, $normalizedProject) use ($currentUser, $user) {
                $normalizedProject['isCollaborating'] = $project->getMembers()->contains($currentUser);

                $invitation = $this->invitationRepository->findOneBy([
                    'project' => $project,
                    'recipient' => $user,
                    'sender' => $currentUser,
                    'type' => 'invitation'
                ]);
                if ($invitation) {
                    $normalizedProject['invitationStatus'] = $invitation->getStatus();
                }

                $receivedInvitation = $this->invitationRepository->findOneBy([
                    'project' => $project,
                    'recipient' => $currentUser,
                    'sender' => $user,
                    'type' => 'invitation'
                ]);
                if ($receivedInvitation) {
                    $normalizedProject['receivedInvitationStatus'] = $receivedInvitation->getStatus();
                }

                $sentRequest = $this->invitationRepository->findOneBy([
                    'project' => $project,
                    'sender' => $currentUser,
                    'recipient' => $user,
                    'type' => 'request'
                ]);
                if ($sentRequest) {
                    $normalizedProject['requestStatus'] = $sentRequest->getStatus();
                }

                $receivedRequest = $this->invitationRepository->findOneBy([
                    'project' => $project,
                    'sender' => $user,
                    'recipient' => $currentUser,
                    'type' => 'request'
                ]);
                if ($receivedRequest) {
                    $normalizedProject['receivedRequestStatus'] = $receivedRequest->getStatus();
                }

                return $normalizedProject;
            }, $publicProjects, $normalizedProjects);

            $responseData['projects'] = $projectsWithStatus;
        }
        if ($user->isRolesPublic()) {
            $responseData['roles'] = $user->getRoles();
        }
        return $responseData;
    }

    #[Route('/api/users/current/projects/{targetUserId}', name: 'get_projects_from_current_user', methods: ['GET'])]
    public function getCurrentUserProjects(Request $request, int $targetUserId): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
            }
            $targetUser = null;
            if ($targetUserId) {
                $targetUser = $this->userRepository->find($targetUserId);
                if (!$targetUser) {
                    return $this->json(['error' => 'Target user not found'], JsonResponse::HTTP_NOT_FOUND);
                }
            }

            $userProjects = array_filter($user->getProjects()->toArray(), function ($project) {
                return is_object($project) && method_exists($project, 'isPublic') && $project->isPublic();
            });

            $responseData = [];
            $normalizedProjects = $this->serializer->normalize($userProjects, null, ['groups' => ['user']]);

            if ($targetUser) {
                foreach ($normalizedProjects as &$project) {
                    $invitation = $this->invitationRepository->findOneBy([
                        'project' => $project['id'],
                        'recipient' => $targetUser,
                        'sender' => $user,
                        'type' => 'invitation'
                    ]);

                    if ($invitation) {
                        $project['invitationStatus'] = $invitation->getStatus();
                    }

                    $request = $this->invitationRepository->findOneBy([
                        'project' => $project['id'],
                        'sender' => $targetUser,
                        'recipient' => $user,
                        'type' => 'request'
                    ]);

                    if ($request) {
                        $project['requestStatus'] = $request->getStatus();
                    }

                    $projectEntity = $this->projectRepository->find($project['id']);
                    if ($projectEntity->getMembers()->contains($targetUser)) {
                        $project['isCollaborating'] = true;
                    } else {
                        $project['isCollaborating'] = false;
                    }
                }
            }

            $responseData['projects'] = $normalizedProjects;
            return $this->json($responseData);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Unexpected error occurred', 'details' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/users/projects/{id}', name: 'get_projects', methods: ['GET'])]
    public function getUserProjects(Request $request, int $id): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
            }

            $targetUser = $this->userRepository->find($id);
            if (!$targetUser) {
                return $this->json(['error' => 'Target user not found'], JsonResponse::HTTP_NOT_FOUND);
            }
            $userProjects = $user->getProjects()->toArray();
            $targetUserProjects = $targetUser->getProjects()->toArray();
            $sharedProjects = array_intersect(
                array_map(fn($project) => $project->getId(), $userProjects),
                array_map(fn($project) => $project->getId(), $targetUserProjects)
            );
            $userProjects = array_filter($userProjects, function ($project) use ($sharedProjects) {
                return $project->isPublic() && !in_array($project->getId(), $sharedProjects);
            });

            $userProjectsWithStatus = array_map(function ($project) use ($targetUser) {
                $invitationStatus = null;
                foreach ($project->getInvitations() as $invitation) {
                    if ($invitation->getRecipient() === $targetUser && $invitation->getStatus() === 'pending') {
                        $invitationStatus = 'pending';
                        break;
                    }
                }
                return [
                    'id' => $project->getId(),
                    'name' => $project->getName(),
                    'public' => $project->isPublic(),
                    'invitationStatus' => $invitationStatus
                ];
            }, $userProjects);

            $responseData = [];
            $responseData['projects'] = $this->serializer->normalize($userProjectsWithStatus, null, ['groups' => ['user']]);
            return $this->json($responseData);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Unexpected error occurred', 'details' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/users/member/{id}', name: 'get_profile_member', methods: ['GET'])]
    public function getProfileMember(int $id, ProjectRepository $projectRepository): JsonResponse
    {
        try {
            $currentUser = $this->getUser();
            if (!$currentUser) {
                return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
            }
            $user = $this->userRepository->find($id);
            if (!$user) {
                return $this->json(['error' => 'Profile not found'], JsonResponse::HTTP_NOT_FOUND);
            }
            if ($user->isPublic()) {
                return $this->json($this->getPublicUserData($user, $this->getUser()));
            }
            $projects = $projectRepository->findProjectsByMembers($currentUser, $user);
            if (count($projects) > 0) {
                return $this->json($this->getPublicUserData($user, $this->getUser()));
            }
            return $this->json(['error' => 'Access denied'], JsonResponse::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Unexpected error occurred', 'details' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    #[Route('/api/users/profiles', name: 'get_profiles', methods: ['GET'])]
    public function getProfiles(Request $request): JsonResponse
    {
        $users = $this->userRepository->findAll();
        $publicUsers = array_filter($users, function ($user) {
            return $user->isPublic();
        });
        $profiles = array_map(function ($user) {
            return $this->getPublicUserData($user, $this->getUser());
        }, $publicUsers);

        return $this->json($profiles);
    }


    #[Route('/api/users/profile', name: 'update_profile', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['username'])) {
            $user->setUsername($data['username']);
        }

        if (isset($data['address'])) {
            $user->setAddress($data['address']);
        }

        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        if (isset($data['sacemNumber'])) {
            $user->setSacemNumber($data['sacemNumber']);
        }

        if (isset($data['isPublic'])) {
            $user->setPublic($data['isPublic']);
        }

        if (isset($data['emailPublic'])) {
            $user->setEmailPublic($data['emailPublic']);
        }

        if (isset($data['addressPublic'])) {
            $user->setAddressPublic($data['addressPublic']);
        }

        if (isset($data['phonePublic'])) {
            $user->setPhonePublic($data['phonePublic']);
        }

        if (isset($data['sacemNumberPublic'])) {
            $user->setSacemNumberPublic($data['sacemNumberPublic']);
        }

        if (isset($data['bioPublic'])) {
            $user->setBioPublic($data['bioPublic']);
        }

        if (isset($data['projectsPublic'])) {
            $user->setProjectsPublic($data['projectsPublic']);
        }

        if (isset($data['rolesPublic'])) {
            $user->setRolesPublic($data['rolesPublic']);
        }

        if (isset($data['roles'])) {
            $user->setRoles($data['roles']);
        }

        if (isset($data['bio'])) {
            $user->setBio($data['bio']);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json(['message' => 'Profile updated successfully']);
    }

    #[Route('/api/users/change-password', name: 'change_password', methods: ['POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['oldPassword']) || !isset($data['newPassword'])) {
            return $this->json(['error' => 'Invalid input'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!$passwordHasher->isPasswordValid($user, $data['oldPassword'])) {
            return $this->json(['error' => 'Old password is incorrect'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $data['newPassword']);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json(['message' => 'Password changed successfully']);
    }
}
