<?php

namespace App\Command;

use App\Repository\EventRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\EmailService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


#[AsCommand(
    name: 'app:send-event-reminder',
    description: 'Send reminders for upcoming events.',
)]
class SendEventRemindersCommand extends Command
{

    private $eventRepository;
    private $emailService;

    public function __construct(EventRepository $eventRepository, ParameterBagInterface $params)
    {
        parent::__construct();
        $this->eventRepository = $eventRepository;
        $this->emailService = new EmailService($params);
    }

    protected function configure(): void
    {
        $this->setName('app:send-event-reminder')->setDescription('Send reminders for upcoming events.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $reminderTime = $now->modify('+24 hours');

        $events = $this->eventRepository->findAllEventsUpTo($reminderTime);

        foreach ($events as $event) {
            foreach ($event->getProject()->getMembers() as $user) {
                $eventData = [
                    'username' => $user->getUsername(),
                    'name' => $event->getName(),
                    'location' => $event->getLocation(),
                    'date' => $event->getStartDate()->format('Y-m-d H:i:s')
                ];

                $emailData = $this->emailService->getEventReminderEmail($eventData);
                $this->emailService->sendEmail(
                    $user->getEmail(),
                    $emailData['subject'],
                    $emailData['body'],
                    $emailData['altBody'],
                    $emailData['fromSubject']
                );
            }
        }
        return Command::SUCCESS;
    }
}
