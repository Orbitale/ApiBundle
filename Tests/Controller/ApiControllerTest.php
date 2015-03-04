<?php
/*
* This file is part of the PierstovalApiBundle package.
*
* (c) Alexandre "Pierstoval" Rock Ancelet <pierstoval@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Pierstoval\Bundle\ApiBundle\Tests\Controller;

use Pierstoval\Bundle\ApiBundle\Tests\Fixtures\AbstractTestCase;

class ApiControllerTest extends AbstractTestCase
{

    public function testDbNotEmpty()
    {
        $objects = $this->em->getRepository($this->entityClass)->findAll();
        $this->assertNotEmpty($objects);
    }

}
