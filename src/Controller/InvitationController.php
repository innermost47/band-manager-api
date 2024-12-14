<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\EmailService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\NotificationService;

#[Route('/api/invitations')]
class InvitationController extends AbstractController
{
    private $entityManager;
    private $projectRepository;
    private $invitationRepository;
    private $emailService;
    private $notificationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProjectRepository $projectRepository,
        InvitationRepository $invitationRepository,
        ParameterBagInterface $params,
        NotificationService $notificationService
    ) {
        $this->entityManager = $entityManager;
        $this->projectRepository = $projectRepository;
        $this->invitationRepository = $invitationRepository;
        $this->emailService = new EmailService($params);
        $this->notificationService = $notificationService;
    }

    #[Route('/send', name: 'send_invitation', methods: ['POST'])]
    public function sendInvitation(Request $request, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $recipientId = $data['recipientId'] ?? null;
        $projectId = $data['projectId'] ?? null;

        if (!$recipientId || !$projectId) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        $currentUser = $this->getUser();
        $project = $this->projectRepository->find($projectId);
        $isOwner = $project->getMembers()->contains($currentUser);

        if (!$project || !$isOwner) {
            return $this->json(['error' => 'Project not found or access denied'], JsonResponse::HTTP_NOT_FOUND);
        }

        $recipient = $userRepository->find($recipientId);
        if (!$recipient) {
            return $this->json(['error' => 'Recipient not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $hasSharedProject = $this->projectRepository->isSharedWithUser($recipientId, $currentUser->getId());
        if (!$hasSharedProject && !$recipient->isPublic()) {
            return $this->json(
                ['error' => 'No shared project with recipient or recipient does not have a public profile'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        if ($project->getMembers()->contains($recipient)) {
            return $this->json(
                ['error' => 'User is already a member of this project'],
                JsonResponse::HTTP_CONFLICT
            );
        }

        $invitation = new Invitation();
        $invitation->setSender($this->getUser());
        $invitation->setRecipient($recipient);
        $invitation->setEmail($recipient->getEmail());
        $invitation->setUsername($recipient->getUsername());
        $invitation->setProject($project);
        $invitation->setStatus('pending');
        $invitation->setToken(bin2hex(random_bytes(16)));
        $invitation->setType('invitation');

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $recipientEmail = $recipient->getEmail();
        $emailData = $this->emailService->getProjectInvitationEmail($project->getName());
        $isEmailSent = $this->emailService->sendEmail(
            $recipientEmail,
            $emailData['subject'],
            $emailData['body'],
            $emailData['altBody'],
            $emailData['fromSubject']
        );
        if ($isEmailSent) {
            $this->notificationService->createSingleNotification(
                $recipient,
                sprintf(
                    '%s invited you to join the project "%s"',
                    $currentUser->getUsername(),
                    $project->getName()
                ),
                'project_invitation_received',
                '/profile',
                $project,
                [
                    'senderName' => $currentUser->getUsername(),
                    'projectName' => $project->getName()
                ]
            );
            return new JsonResponse(
                ['message' => 'Invitation sent successfully.'],
                JsonResponse::HTTP_OK
            );
        } else {
            return new JsonResponse(
                ['message' => 'Invitation could not be sent. Please try again later.'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/request', name: 'request_collaboration', methods: ['POST'])]
    public function requestCollaboration(Request $request, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $projectId = $data['projectId'] ?? null;
        $targetId = $data['targetId'] ?? null;

        if (!$projectId || !$targetId) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        $currentUser = $this->getUser();
        $project = $this->projectRepository->find($projectId);
        $targetUser = $userRepository->find($targetId);

        if (!$project) {
            return $this->json(['error' => 'Project not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (!$targetUser) {
            return $this->json(['error' => 'Target user not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (!$project->isPublic()) {
            return $this->json(['error' => 'Project is not public'], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($project->getMembers()->contains($currentUser)) {
            return $this->json(['error' => 'You are already a member of this project'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!$project->getMembers()->contains($targetUser)) {
            return $this->json(['error' => 'Target user is not a member of this project'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $existingInvitation = $this->invitationRepository->findOneBy([
            'recipient' => $currentUser,
            'project' => $project,
            'status' => ['pending', 'revoked', 'declined']
        ]);

        if ($existingInvitation) {
            $errorMessage = match ($existingInvitation->getStatus()) {
                'pending' => 'A request is already pending for this project',
                'revoked' => 'You cannot request to join this project as your previous access was revoked',
                'declined' => 'You cannot request to join this project as your previous request was declined',
                default => 'Cannot process request for this project'
            };

            return $this->json(['error' => $errorMessage], JsonResponse::HTTP_BAD_REQUEST);
        }

        $invitation = new Invitation();
        $invitation->setSender($currentUser);
        $invitation->setRecipient($targetUser);
        $invitation->setEmail($currentUser->getEmail());
        $invitation->setUsername($currentUser->getUsername());
        $invitation->setProject($project);
        $invitation->setStatus('pending');
        $invitation->setToken(bin2hex(random_bytes(16)));
        $invitation->setType('request');

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $recipientEmail = $targetUser->getEmail();
        $emailData = $this->emailService->getCollaborationRequestEmail($currentUser->getUsername(), $project->getName());
        $isEmailSent = $this->emailService->sendEmail(
            $recipientEmail,
            $emailData['subject'],
            $emailData['body'],
            $emailData['altBody'],
            $emailData['fromSubject']
        );
        if ($isEmailSent) {
            $this->notificationService->createSingleNotification(
                $targetUser,
                sprintf(
                    '%s requested to join your project "%s"',
                    $currentUser->getUsername(),
                    $project->getName()
                ),
                'collaboration_request_received',
                '/profile',
                $project,
                [
                    'requesterName' => $currentUser->getUsername(),
                    'projectName' => $project->getName()
                ]
            );
            return new JsonResponse(
                ['message' => 'Collaboration request sent successfully.'],
                JsonResponse::HTTP_OK
            );
        } else {
            return new JsonResponse(
                ['message' => 'Request could not be sent. Please try again later.'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/accept/{token}', name: 'accept_invitation', methods: ['POST'])]
    public function acceptInvitation(string $token): JsonResponse
    {
        $invitation = $this->invitationRepository->findOneBy(['token' => $token]);

        if (!$invitation) {
            return $this->json(['error' => 'Invalid or expired token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($invitation->getStatus() !== 'pending') {
            return $this->json(['error' => 'Request already processed'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($invitation->getRecipient() !== $this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $project = $this->projectRepository->find($invitation->getProject());
        $recipient = $invitation->getRecipient();
        $sender = $invitation->getSender();

        if (!$project || !$recipient) {
            return $this->json(['error' => 'Project or recipient not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($invitation->getType() === 'request') {
            $project->addMember($sender);
        } else {
            $project->addMember($recipient);
        }
        $invitation->setStatus('accepted');
        $this->entityManager->persist($project);
        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $recipientEmail = $sender->getEmail();
        $emailData = $this->emailService->getAcceptanceEmail($project->getName(), $invitation->getType() === 'request');
        $isEmailSent = $this->emailService->sendEmail(
            $recipientEmail,
            $emailData['subject'],
            $emailData['body'],
            $emailData['altBody'],
            $emailData['fromSubject']
        );
        if ($isEmailSent) {
            $notificationRecipient = $invitation->getType() === 'request' ? $sender : $recipient;
            $notificationSender = $invitation->getType() === 'request' ? $recipient : $sender;

            $this->notificationService->createSingleNotification(
                $notificationRecipient,
                $invitation->getType() === 'request'
                    ? sprintf(
                        'Your request to join "%s" has been accepted by %s',
                        $project->getName(),
                        $notificationSender->getUsername()
                    )
                    : sprintf(
                        'Your invitation to "%s" has been accepted by %s',
                        $project->getName(),
                        $notificationSender->getUsername()
                    ),
                $invitation->getType() === 'request' ? 'collaboration_request_accepted' : 'project_invitation_accepted',
                sprintf(
                    '/projects/%d',
                    $project->getId()
                ),
                $project,
                [
                    'projectName' => $project->getName(),
                    'senderName' => $notificationSender->getUsername(),
                    'type' => $invitation->getType()
                ]
            );
            return new JsonResponse(
                ['message' => $invitation->getType() === 'request' ? 'Request accepted.' : 'Invitation accepted.'],
                JsonResponse::HTTP_OK
            );
        } else {
            return new JsonResponse(
                ['message' => 'Confirmation email could not be sent. Please try again later.'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/decline/{token}', name: 'decline_invitation', methods: ['POST'])]
    public function declineInvitation(string $token): JsonResponse
    {
        $invitation = $this->invitationRepository->findOneBy(['token' => $token]);

        if ($invitation->getRecipient() !== $this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], JsonResponse::HTTP_FORBIDDEN);
        }

        $sender = $invitation->getSender();
        $project = $invitation->getProject();
        $invitation->setStatus('declined');
        $this->entityManager->flush();

        $recipientEmail = $sender->getEmail();
        $emailData = $this->emailService->getDeclineEmail($project->getName(), $invitation->getType() === 'request');
        $isEmailSent = $this->emailService->sendEmail(
            $recipientEmail,
            $emailData['subject'],
            $emailData['body'],
            $emailData['altBody'],
            $emailData['fromSubject']
        );
        if ($isEmailSent) {
            $this->notificationService->createSingleNotification(
                $sender,
                $invitation->getType() === 'request'
                    ? sprintf('Your request to join "%s" has been declined', $project->getName())
                    : sprintf(
                        'Your invitation to "%s" has been declined by %s',
                        $project->getName(),
                        $invitation->getRecipient()->getUsername()
                    ),
                $invitation->getType() === 'request' ? 'collaboration_request_declined' : 'project_invitation_declined',
                sprintf(
                    '/public-projects/%d',
                    $project->getId()
                ),
                $project,
                [
                    'projectName' => $project->getName(),
                    'recipientName' => $invitation->getRecipient()->getUsername(),
                    'type' => $invitation->getType()
                ]
            );
            return new JsonResponse(
                ['message' => $invitation->getType() === 'request' ? 'Request declined.' : 'Invitation declined.'],
                JsonResponse::HTTP_OK
            );
        } else {
            return new JsonResponse(
                ['message' => 'Notification email could not be sent. Please try again later.'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/cancel/{token}', name: 'cancel_invitation', methods: ['POST'])]
    public function cancelInvitation(string $token): JsonResponse
    {
        $invitation = $this->invitationRepository->findOneBy(['token' => $token]);

        if (!$invitation || $invitation->getSender() !== $this->getUser()) {
            return $this->json(['error' => 'Unauthorized or request not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $project = $invitation->getProject();
        $recipientEmail = $invitation->getRecipient()
            ? $invitation->getRecipient()->getEmail()
            : $invitation->getEmail();

        $this->entityManager->remove($invitation);
        $this->entityManager->flush();

        $emailData = $this->emailService->getCancellationEmail($project->getName(), $invitation->getType() === 'request');
        $isEmailSent = $this->emailService->sendEmail(
            $recipientEmail,
            $emailData['subject'],
            $emailData['body'],
            $emailData['altBody'],
            $emailData['fromSubject']
        );
        if ($isEmailSent) {
            return new JsonResponse(
                ['message' => $invitation->getType() === 'request' ? 'Request cancelled.' : 'Invitation cancelled.'],
                JsonResponse::HTTP_OK
            );
        } else {
            return new JsonResponse(
                ['message' => 'Cancellation notification could not be sent. Please try again later.'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/invite-by-email', name: 'invite_by_email', methods: ['POST'])]
    public function inviteByEmail(Request $request, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $projectId = $data['projectId'] ?? null;

        if (!$email || !$projectId) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        $currentUser = $this->getUser();
        $project = $this->projectRepository->find($projectId);

        $isOwner = $project->getMembers()->contains($currentUser);
        if (!$project || !$isOwner) {
            return $this->json(['error' => 'Project not found or you are not the owner'], JsonResponse::HTTP_FORBIDDEN);
        }

        $totalUsers = $userRepository->count([]);
        $maxUsers = $this->getParameter('max_users');
        if ($totalUsers >= ($maxUsers - 5)) {
            return $this->json(
                ['error' => 'Maximum number of users almost reached. New invitations are disabled.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $acceptedInvitation = $this->entityManager->getRepository(Invitation::class)->findOneBy([
            'email' => $email,
            'project' => $project,
            'status' => 'accepted'
        ]);

        if ($acceptedInvitation) {
            return $this->json(
                ['error' => 'This user is already a member of the project'],
                JsonResponse::HTTP_CONFLICT
            );
        }

        $lastInvitation = $this->invitationRepository->findOneBy(
            ['email' => $email, 'project' => $project],
            ['createdAt' => 'DESC']
        );

        $now = new \DateTimeImmutable();

        if ($lastInvitation) {
            if ($lastInvitation->getStatus() === 'pending' && $lastInvitation->getExpiresAt() > $now) {
                return $this->json(
                    ['error' => 'An invitation is already pending for this email in this project'],
                    JsonResponse::HTTP_CONFLICT
                );
            }

            if ($lastInvitation->getExpiresAt() <= $now) {
                if ($lastInvitation->getAttempts() >= 3) {
                    return $this->json(
                        ['error' => 'Maximum number of invitation attempts reached. Please contact support.'],
                        JsonResponse::HTTP_FORBIDDEN
                    );
                }
                $waitingTime = 3600;
                $timeSinceLastInvitation = $now->getTimestamp() - $lastInvitation->getExpiresAt()->getTimestamp();
                if ($timeSinceLastInvitation < $waitingTime) {
                    $remainingTime = ceil(($waitingTime - $timeSinceLastInvitation) / 60);
                    return $this->json(
                        ['error' => "Please wait {$remainingTime} minutes before sending a new invitation."],
                        JsonResponse::HTTP_TOO_MANY_REQUESTS
                    );
                }
            }
        }

        $existingUser = $userRepository->findOneBy(['email' => $email]);

        $invitationCode = strtoupper(substr(bin2hex(random_bytes(8)), 0, 8));

        $invitation = new Invitation();
        $invitation->setSender($currentUser);
        $invitation->setEmail($email);
        $invitation->setUsername($email);
        $invitation->setProject($project);
        $invitation->setStatus('pending');
        $invitation->setToken($invitationCode);
        $invitation->setType('code_invitation');
        $invitation->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $invitation->setAttempts($lastInvitation ? $lastInvitation->getAttempts() + 1 : 1);

        if ($existingUser) {
            $invitation->setRecipient($existingUser);
            $invitation->setUsername($existingUser->getUsername());
        }

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $emailData = [
            'isRegistered' => $existingUser !== null,
            'username' => $existingUser ? $existingUser->getUsername() : null,
            'projectName' => $project->getName(),
            'invitationCode' => $invitationCode,
            'registrationUrl' => $this->getParameter('frontend_url') . '/signup'
        ];

        $emailData = $this->emailService->getCodeInvitationEmail($emailData);

        $isEmailSent = $this->emailService->sendEmail(
            $email,
            $emailData['subject'],
            $emailData['body'],
            $emailData['altBody'],
            $emailData['fromSubject']
        );

        if ($isEmailSent) {
            return new JsonResponse(
                ['message' => 'Invitation sent successfully.'],
                JsonResponse::HTTP_OK
            );
        } else {
            return new JsonResponse(
                ['message' => 'Invitation could not be sent. Please try again later.'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/join-with-code', name: 'join_with_code', methods: ['POST'])]
    public function joinWithCode(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? null;

        if (!$code) {
            return $this->json(['error' => 'Missing invitation code'], 400);
        }

        $invitation = $this->invitationRepository->findOneBy([
            'token' => $code,
            'type' => 'code_invitation',
            'status' => 'pending'
        ]);

        if (!$invitation || $invitation->getExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['error' => 'Invalid or expired invitation code'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $currentUser = $this->getUser();
        if ($invitation->getEmail() !== $currentUser->getEmail()) {
            return $this->json(['error' => 'This invitation code is not associated with your email'], 403);
        }

        $project = $invitation->getProject();
        $project->addMember($currentUser);
        $invitation->setStatus('accepted');
        $invitation->setRecipient($currentUser);

        $this->entityManager->persist($project);
        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $sender = $invitation->getSender();
        $emailData = [
            'senderName' => $sender->getUsername(),
            'recipientName' => $currentUser->getUsername(),
            'projectName' => $project->getName()
        ];

        $notificationData = $this->emailService->getInvitationAcceptedNotificationEmail($emailData);

        $isEmailSent = $this->emailService->sendEmail(
            $sender->getEmail(),
            $notificationData['subject'],
            $notificationData['body'],
            $notificationData['altBody'],
            $notificationData['fromSubject']
        );

        if ($isEmailSent) {
            $this->notificationService->createSingleNotification(
                $sender,
                sprintf(
                    '%s has joined your project "%s" using the invitation code',
                    $currentUser->getUsername(),
                    $project->getName()
                ),
                'project_invitation_code_used',
                sprintf(
                    '/projects/%d',
                    $project->getId()
                ),
                $project,
                [
                    'newMemberName' => $currentUser->getUsername(),
                    'projectName' => $project->getName()
                ]
            );
            return new JsonResponse(
                ['message' => 'Successfully joined project.'],
                JsonResponse::HTTP_OK
            );
        } else {
            return new JsonResponse(
                ['message' => 'An error occured. Please try again later.'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
