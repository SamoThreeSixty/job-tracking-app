<?php

namespace App\Repository;

use App\Entity\TimeBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeBlock>
 */
class TimeBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeBlock::class);
    }

    public function findActiveBlock(): ?TimeBlock
    {
        return $this->createQueryBuilder('tb')
            ->andWhere('tb.endTime IS NULL')
            ->orderBy('tb.startTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<TimeBlock>
     */
    public function findRecentBlocks(int $limit = 20): array
    {
        return $this->createQueryBuilder('tb')
            ->orderBy('tb.startTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
