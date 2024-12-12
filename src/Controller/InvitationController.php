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

#[Route('/api/invitations')]
class InvitationController extends AbstractController
{
    private $entityManager;
    private $projectRepository;
    private $invitationRepository;
    private $emailService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProjectRepository $projectRepository,
        InvitationRepository $invitationRepository,
        ParameterBagInterface $params
    ) {
        $this->entityManager = $entityManager;
        $this->projectRepository = $projectRepository;
        $this->invitationRepository = $invitationRepository;
        $this->emailService = new EmailService($params);
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
        $recipient = $invitation->getRecipient();

        $this->entityManager->remove($invitation);
        $this->entityManager->flush();

        $recipientEmail = $recipient->getEmail();
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
        if (!$project || $isOwner) {
            return $this->json(['error' => 'Project not found or you are not the owner'], JsonResponse::HTTP_FORBIDDEN);
        }

        $existingUser = $userRepository->findOneBy(['email' => $email]);

        $invitationCode = strtoupper(substr(bin2hex(random_bytes(8)), 0, 8));

        $invitation = new Invitation();
        $invitation->setSender($currentUser);
        $invitation->setEmail($email);
        $invitation->setProject($project);
        $invitation->setStatus('pending');
        $invitation->setToken($invitationCode);
        $invitation->setType('code_invitation');
        $invitation->setExpiresAt(new \DateTime('+7 days'));

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
            'registrationUrl' => $this->getParameter('app.frontend_url') . '/register?invitation=' . $invitationCode
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

        if (!$invitation || $invitation->getExpiresAt() < new \DateTime()) {
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

        $this->emailService->sendEmail(
            $sender->getEmail(),
            $notificationData['subject'],
            $notificationData['body'],
            $notificationData['altBody'],
            $notificationData['fromSubject']
        );

        return new JsonResponse(['message' => 'Successfully joined project'], JsonResponse::HTTP_OK);
    }
}
