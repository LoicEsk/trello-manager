<?php

namespace App\Repository;

use App\Entity\BoardList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method BoardList|null find($id, $lockMode = null, $lockVersion = null)
 * @method BoardList|null findOneBy(array $criteria, array $orderBy = null)
 * @method BoardList[]    findAll()
 * @method BoardList[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BoardListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoardList::class);
    }

    // /**
    //  * @return BoardList[] Returns an array of BoardList objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?BoardList
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
