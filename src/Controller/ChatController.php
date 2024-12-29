<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Message;
use App\Entity\Project;
use App\Repository\ChannelRepository;
use App\Repository\MessageRepository;
use App\Repository\ProjectRepository;
use App\Service\NotificationService;
use App\Service\ProjectService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/channels')]
class ChatController extends AbstractController
{
    private $entityManager;
    private $projectRepository;
    private $projectService;
    private $messageRepository;
    private $channelRepository;
    private $notificationService;

    public function __construct(EntityManagerInterface $entityManager, ProjectRepository $projectRepository, ProjectService $projectService, MessageRepository $messageRepository, ChannelRepository $channelRepository, NotificationService $notificationService)
    {
        $this->entityManager = $entityManager;
        $this->projectRepository = $projectRepository;
        $this->projectService = $projectService;
        $this->messageRepository = $messageRepository;
        $this->channelRepository = $channelRepository;
        $this->notificationService = $notificationService;
    }

    #[Route('/chat/projects/{id}', methods: ['GET'])]
    public function getOrCreateProjectChannel($id): Response
    {
        $project = $this->projectRepository->find($id);
        $this->projectService->verifyProjectAccess($project, $this->getUser());

        $channel = $this->channelRepository->findOneBy([
            'project' => $project,
            'type' => 'project_main'
        ]);

        if (!$channel) {
            $channel = new Channel();
            $channel->setName($project->getName());
            $channel->setType('project_main');
            $channel->setProject($project);
            $channel->setCreatedAt(new DateTimeImmutable());
            $this->entityManager->persist($channel);
            $this->entityManager->flush();
        }

        return $this->json($channel, 200, [], ['groups' => ['channel:read']]);
    }

    #[Route('/projects/{id}', methods: ['GET'])]
    public function getProjectChannels(Project $project, $id): Response
    {
        $project = $this->projectRepository->find($id);
        $this->projectService->verifyProjectAccess($project, $this->getUser());

        $channels = $project->getChannels();
        return $this->json($channels, 200, [], ['groups' => ['channel:read']]);
    }

    #[Route('/projects/{id}', methods: ['POST'])]
    public function createChannel(Request $request, $id): Response
    {
        $project = $this->projectRepository->find($id);
        $this->projectService->verifyProjectAccess($project, $this->getUser());

        $data = json_decode($request->getContent(), true);

        $channel = new Channel();
        $channel->setName($data['name']);
        $channel->setType($data['type']);
        $channel->setProject($project);

        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        $this->notificationService->notifyProjectMembers(
            sprintf(
                '%s created the channel "%s"',
                $this->getUser()->getUsername(),
                $channel->getName()
            ),
            'channel_created',
            '/chat',
            $project,
            [
                'projectName' => $project->getName(),
                'channelName' => $channel->getName(),
                'createdBy' => $this->getUser()->getUsername()
            ]
        );

        return $this->json($channel, 201);
    }

    #[Route('/{id<\d+>}/messages', methods: ['GET'])]
    public function getMessages(Request $request, $id): Response
    {
        $channel = $this->channelRepository->find($id);
        $project = $channel->getProject();
        $this->projectService->verifyProjectAccess($project, $this->getUser());

        $limit = $request->query->get('limit', 50);
        $before = $request->query->get('before');

        $messages = $this->messageRepository->findChannelMessages(
            $channel,
            $limit,
            $before ? new \DateTime($before) : null
        );

        return $this->json($messages, 200, [], ['groups' => ['message:read']]);
    }

    #[Route('/{id<\d+>}/messages', methods: ['POST'])]
    public function sendMessage(Request $request, $id): Response
    {
        $channel = $this->channelRepository->find($id);
        $project = $channel->getProject();
        $this->projectService->verifyProjectAccess($project, $this->getUser());

        $data = json_decode($request->getContent(), true);

        $message = new Message();
        $message->setContent($data['content']);
        $message->setAuthor($this->getUser());
        $message->setChannel($channel);
        $message->setCreatedAt(new DateTimeImmutable());
        $message->setEditedAt(new DateTimeImmutable());

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->notificationService->notifyProjectMembers(
            sprintf(
                '%s sent a message in the channel "%s"',
                $this->getUser()->getUsername(),
                $channel->getName()
            ),
            'message_sent',
            '/chat',
            $project,
            [
                'projectName' => $project->getName(),
                'channelName' => $channel->getName(),
                'sentBy' => $this->getUser()->getUsername()
            ]
        );
        return $this->json($message, 201, [], ['groups' => ['message:read']]);
    }

    #[Route('/{id<\d+>}/messages/new', methods: ['GET'])]
    public function getNewMessages(Request $request, $id): Response
    {
        try {
            $channel = $this->channelRepository->find($id);
            if (!$channel) {
                return $this->json([], 200);
            }
            $project = $channel->getProject();
            $this->projectService->verifyProjectAccess($project, $this->getUser());
            $after = $request->query->get('after');
            if (!$after) {
                return $this->json([], 200);
            }
            try {
                $afterDate = new \DateTime($after);
                error_log('After timestamp: ' . $afterDate->format('Y-m-d H:i:s'));
                $messages = $this->messageRepository->findNewMessages(
                    $channel,
                    $afterDate
                );
                error_log('Found messages count: ' . count($messages));
                foreach ($messages as $message) {
                    error_log('Message date: ' . $message->getCreatedAt()->format('Y-m-d H:i:s'));
                }
                return $this->json($messages, 200, [], ['groups' => ['message:read']]);
            } catch (\Exception $e) {
                error_log('Date error: ' . $e->getMessage());
                return $this->json([], 200);
            }
        } catch (\Exception $e) {
            error_log('General error: ' . $e->getMessage());
            return $this->json([], 200);
        }
    }
}
