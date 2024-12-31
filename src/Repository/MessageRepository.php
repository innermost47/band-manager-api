<?php

namespace App\Repository;

use App\Entity\Channel;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findChannelMessages(Channel $channel, int $limit = 50, ?\DateTime $before = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.channel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($before) {
            $qb->andWhere('m.createdAt < :before')
                ->setParameter('before', $before);
        }

        return $qb->getQuery()->getResult();
    }
    public function findNewMessages(Channel $channel, \DateTimeImmutable $after): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.channel = :channel')
            ->andWhere('m.createdAt >= :after')
            ->setParameter('channel', $channel)
            ->setParameter('after', $after->modify('+1 microsecond'))
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
};
