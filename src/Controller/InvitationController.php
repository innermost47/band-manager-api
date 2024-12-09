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
        $subject = 'You\'ve been invited to join a project';
        $body = "Hello,
        
        You have been invited to join the project: " . $project->getName() . ".
        
        To respond to this invitation, please visit your profile page and review the invitation.
        
        If you have any questions, feel free to contact us.
        
        Best regards,
        Band Manager";
        $altBody = "Hello,
        
        You have been invited to join the project: " . $project->getName() . ".
        
        To respond to this invitation, visit your profile page and review the invitation.
        
        Best regards,
        Band Manager";
        $fromSubject = 'Project Invitation';
        $isEmailSent = $this->emailService->sendEmail($recipientEmail, $subject, $body,  $altBody, $fromSubject);
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
        $subject = 'New Collaboration Request';
        $fromSubject = 'Collaboration Request';
        $body = "Hello,
        
        " . $currentUser->getUsername() . " has requested to join your project: " . $project->getName() . ".
        
        To respond to this request, please visit your project management page.
        
        If you have any questions, feel free to contact us.
        
        Best regards,
        Band Manager";

        $altBody = "Hello,
        
        " . $currentUser->getUsername() . " has requested to join your project: " . $project->getName() . ".
        
        To respond to this request, visit your project management page.
        
        Best regards,
        Band Manager";
        $isEmailSent = $this->emailService->sendEmail($recipientEmail, $subject, $body,  $altBody, $fromSubject);

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
        $subject = $invitation->getType() === 'request' ? 'Collaboration Request Accepted' : 'Invitation Accepted';
        $body = "";
        if ($invitation->getType() === 'request') {
            $body = "Hello,
            
            We are happy to inform you that your request to join the project: {$project->getName()} has been accepted.
            
            You are now part of this project.
            
            If you have any further questions, feel free to contact us.
            
            Best regards,  
            Band Manager";
        } else {
            $body = "Hello,
            
            We are happy to inform you that the invitation to join the project: {$project->getName()} has been accepted.
            
            The invited member is now part of your project.
            
            If you have any further questions, feel free to contact us.
            
            Best regards,  
            Band Manager";
        }

        $altBody = $body;
        $isEmailSent = $this->emailService->sendEmail($recipientEmail, $subject, $body,  $altBody, $subject);

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
        $subject = $invitation->getType() === 'request' ? 'Collaboration Request Declined' : 'Invitation Declined';
        $body = "";

        if ($invitation->getType() === 'request') {
            $body = "Hello,
            
            We regret to inform you that your request to join the project: {$project->getName()} has been declined.
            
            If you have any questions, feel free to contact us.
            
            Best regards,  
            Band Manager";
        } else {
            $body = "Hello,
            
            We regret to inform you that the invitation to join the project: {$project->getName()} has been declined by the recipient.
            
            If you wish to send another invitation or have any questions, feel free to contact us.
            
            Best regards,  
            Band Manager";
        }

        $altBody = $body;
        $isEmailSent = $this->emailService->sendEmail($recipientEmail, $subject, $body,  $altBody, $subject);

        if ($isEmailSent->send()) {
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
        $subject = $invitation->getType() === 'request' ? 'Collaboration Request Cancelled' : 'Invitation Cancelled';
        $body = "";

        if ($invitation->getType() === 'request') {
            $body = "Hello,
            
            The collaboration request for the project: {$project->getName()} has been cancelled.
            
            If this was not intentional or you need assistance, feel free to contact us.
            
            Best regards,  
            Band Manager";
        } else {
            $body = "Hello,
            
            The invitation to join the project: {$project->getName()} has been cancelled by the sender.
            
            If this was not intentional or you need assistance, feel free to contact us.
            
            Best regards,  
            Band Manager";
        }

        $altBody = $body;
        $isEmailSent = $this->emailService->sendEmail($recipientEmail, $subject, $body,  $altBody, $subject);

        if ($isEmailSent->send()) {
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
}
