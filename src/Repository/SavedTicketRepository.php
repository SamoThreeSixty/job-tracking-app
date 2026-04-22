<?php

namespace App\Repository;

use App\Entity\SavedTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SavedTicket>
 */
class SavedTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavedTicket::class);
    }

    /**
     * @return list<SavedTicket>
     */
    public function findAllForPicker(): array
    {
        return $this->createQueryBuilder('st')
            ->orderBy('st.updatedAt', 'DESC')
            ->addOrderBy('st.ticket', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByTicket(string $ticket): ?SavedTicket
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.ticket = :ticket')
            ->setParameter('ticket', $ticket)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
