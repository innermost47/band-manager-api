<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }
    
    public function findByMember(User $user)
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.members', 'm')
            ->where('m.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getResult();
    }
    
    public function findByProfileImage(string $filename): ?Project
    {
        return $this->findOneBy(['profileImage' => $filename]);
    }
    
    public function findProjectsByMembers($user1, $user2)
    {
        return $this->createQueryBuilder('p')
            ->join('p.members', 'm1')
            ->join('p.members', 'm2')
            ->where('m1 = :user1')
            ->andWhere('m2 = :user2')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->getQuery()
            ->getResult();
    }
    
    public function isSharedWithUser(int $userId1, int $userId2): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.members', 'm1')
            ->innerJoin('p.members', 'm2')
            ->where('m1.id = :userId1')
            ->andWhere('m2.id = :userId2')
            ->setParameter('userId1', $userId1)
            ->setParameter('userId2', $userId2);
    
        return (bool) $qb->getQuery()->getSingleScalarResult();
    }



    //    /**
    //     * @return Project[] Returns an array of Project objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Project
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
