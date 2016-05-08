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

interface ApiRepositoryInterface
{

    /**
     * @return array[]
     */
    public function findAllForApi();

    /**
     * @param string $id
     *
     * @return array
     */
    public function findOneForApi($id);

    /**
     * @param string $entityName
     * @param string $id
     * @param array $subElements
     *
     * @return array
     */
    public function findSubElement($entityName, $id, array $subElements);

}
