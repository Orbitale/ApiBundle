<?php
/*
 * This file is part of the OrbitaleApiBundle package.
 *
 * (c) Alexandre Rock Ancelet <contact@orbitale.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Orbitale\Bundle\ApiBundle\Form;

use Doctrine\ORM\EntityManager;
use Orbitale\Bundle\ApiBundle\Config\EntityConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\Mapping\ClassMetadata;

class ApiFormType extends AbstractType
{
    /**
     * @var EntityConfig
     */
    protected $configurator;

    public function __construct(EntityConfig $configurator)
    {
        $this->configurator = $configurator;
    }

    public function getName()
    {
        return 'orbitale_api_form_type';
    }
}
