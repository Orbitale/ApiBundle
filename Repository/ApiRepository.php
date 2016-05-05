<?php
/*
 * This file is part of the OrbitaleApiBundle package.
 *
 * (c) Alexandre Rock Ancelet <contact@orbitale.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Orbitale\Bundle\ApiBundle\Repository;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;

/**
 * These default functions return an array and are easily overridable.
 * The goal is to let users define their own repository and just extend this one.
 *
 * @todo Check if a trait would be better.
 */
class ApiRepository extends EntityRepository
{
    /**
     * @return array[]
     */
    public function findAllForApi()
    {
        return $this
            ->createQueryBuilder('entity')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * @return array[]
     */
    public function findOneForApi($id)
    {
        return $this
            ->createQueryBuilder('entity')
            ->where('entity.'.$this->getClassMetadata()->getSingleIdentifierFieldName().' = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY)
        ;
    }

}
