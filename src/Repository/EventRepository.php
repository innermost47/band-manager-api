<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findEventsByProject(Project $project): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.project = :project')
            ->setParameter('project', $project)
            ->orderBy('e.recurrence_type', 'DESC')
            ->addOrderBy('e.start_date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPublicEventsByProject(Project $project): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.project = :project')
            ->andWhere('e.isPublic = true')
            ->setParameter('project', $project)
            ->orderBy('e.start_date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllPublicEvents(): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.project', 'p')
            ->andWhere('e.isPublic = true')
            ->andWhere('p.isPublic = true')
            ->orderBy('e.start_date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findEventsInPeriod(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.start_date >= :startDate')
            ->andWhere('e.start_date <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('e.start_date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllEventsUpTo(\DateTimeImmutable $date): array
    {
        $events = $this->findAll();
        $occurrences = [];

        foreach ($events as $event) {
            $start = $event->getStartDate();
            if (!$event->getRecurrenceType()) {
                if ($start <= $date) {
                    $occurrences[] = $event;
                }
                continue;
            }
            $end = $event->getRecurrenceEnd() ?: $date;
            $interval = $event->getRecurrenceInterval() ?: 1;

            while ($start <= $end && $start <= $date) {
                $occurrences[] = clone $event;
                $start = match ($event->getRecurrenceType()) {
                    'DAILY' => $start->modify("+{$interval} days"),
                    'WEEKLY' => $start->modify("+{$interval} weeks"),
                    'BI_WEEKLY' => $start->modify("+" . (2 * $interval) . " weeks"),
                    'MONTHLY' => $start->modify("+{$interval} months"),
                    'YEARLY' => $start->modify("+{$interval} years"),
                    default => null,
                };
                if (!$start) {
                    break;
                }
            }
        }
        return $occurrences;
    }
}
