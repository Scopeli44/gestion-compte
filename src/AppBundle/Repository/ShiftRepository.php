<?php

namespace AppBundle\Repository;

/**
 * ShiftRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ShiftRepository extends \Doctrine\ORM\EntityRepository
{

    public function findFutures($roles)
    {
        $qb = $this->createQueryBuilder('s');

        //->andWhere('s.role IN :roles')
        //->orWhere('s.role IS NULL')
        //->setParameter('roles', $roles)

        $qb
            ->where('s.start > :now')
            ->setParameter('now', new \Datetime('now'))
            ->orderBy('s.start', 'ASC');

        return $qb
            ->getQuery()
            ->getResult();
    }

    /**
     * @param $user
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function findFirst($user)
    {
        $qb = $this->createQueryBuilder('s');

        $qb
            ->join('s.shifter', "ben")
            ->where('ben.user = :user')
            ->setParameter('user', $user)
            ->andWhere('s.isDismissed = 0')
            ->orderBy('s.start', 'ASC')
            ->setMaxResults(1);

        return $qb
            ->getQuery()
            ->getOneOrNullResult();
    }
}
