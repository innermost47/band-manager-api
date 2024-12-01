<?php

namespace App\Command;

use App\Repository\EventRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SendEventRemindersCommand extends Command
{
    private $eventRepository;
    private $mailer;

    public function __construct(EventRepository $eventRepository, MailerInterface $mailer)
    {
        parent::__construct('app:send-event-reminders');
        $this->eventRepository = $eventRepository;
        $this->mailer = $mailer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $reminderThreshold = $now->modify('+24 hours');
        $events = $this->eventRepository->findUpcomingEvents($reminderThreshold);

        foreach ($events as $event) {
            foreach ($event->getParticipants() as $participant) {
                $email = (new Email())
                    ->from($_ENV['MAILER_FROM'])
                    ->to($participant->getEmail())
                    ->subject("Reminder: {$event->getName()}")
                    ->text("Don't forget the upcoming event: {$event->getName()} happening on {$event->getStartDate()->format('Y-m-d H:i')}.");

                $this->mailer->send($email);
            }
        }

        return Command::SUCCESS;
    }
}
