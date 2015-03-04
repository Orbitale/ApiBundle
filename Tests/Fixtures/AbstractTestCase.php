<?php
/*
* This file is part of the PierstovalApiBundle package.
*
* (c) Alexandre "Pierstoval" Rock Ancelet <pierstoval@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Pierstoval\Bundle\ApiBundle\Tests\Fixtures;

use AppKernel;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Pierstoval\Bundle\ApiBundle\Controller\ApiController;
use Pierstoval\Bundle\ApiBundle\Tests\Fixtures\ApiDataTestBundle\Entity\ApiData;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractTestCase extends \PHPUnit_Framework_TestCase
{

    /**
     * @var array
     */
    protected $entityFixtures = array();

    /**
     * @var string
     */
    protected $entityClass = 'Pierstoval\Bundle\ApiBundle\Tests\Fixtures\ApiDataTestBundle\Entity\ApiData';

    /**
     * @var AppKernel
     */
    protected $kernel;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var ApiController
     */
    protected $controller;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        // Boot the AppKernel in the test environment and with the debug.
        $this->kernel = new AppKernel('test', true);
        $this->kernel->boot();

        // Store the container and the entity manager in test case properties
        $this->container  = $this->kernel->getContainer();
        $this->controller = $this->container->get('pierstoval_api_test_controller');
        $this->em         = $this->container->get('doctrine')->getManager();

        $this->controller->setContainer($this->container);

        // Build the schema for sqlite
        $this->generateSchema();

        // Add datas in the database
        $this->addFixtures();

        parent::setUp();
    }

    public function createRequest($method = 'GET', $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = null)
    {
        $this->container->enterScope('request');

        $server = array_replace(array(
            'HTTP_ORIGIN' => 'http://localhost/',
        ), $server);

        $httpRequest = Request::create('/', $method, $parameters, $cookies, $files, $server, $content);

        $this->container->set('request', $httpRequest, 'request');

        return $httpRequest;
    }

    protected function generateSchema()
    {
        // Get the metadata of the application to create the schema.
        $metadata = $this->getMetadata();

        if (!empty($metadata)) {
            // Create SchemaTool
            $tool = new SchemaTool($this->em);
            $tool->createSchema($metadata);
        } else {
            throw new SchemaException('No Metadata Classes to process.');
        }
    }

    protected function generateFixtures()
    {
        if (count($this->entityFixtures)) {
            return $this->entityFixtures;
        }
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        $length = pow(10, 3);
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        $this->entityFixtures = array(
            array('First one', 1, 'this text should be hidden'),
            array('Second one', -1, 'this text should also be hidden'),
            array('Second one', $length, 'And another long text (care, it\'s long):'.$randomString),
        );
        return $this->entityFixtures;
    }

    protected function addFixtures()
    {
        $entities = $this->generateFixtures();
        foreach ($entities as $entity) {
            /** @var ApiData $object */
            $object = new $this->entityClass();
            $object
                ->setName($entity[0])
                ->setValue($entity[1])
                ->setHidden($entity[2])
            ;
            $this->em->persist($object);
        }
        $this->em->flush();
    }

    /**
     * Overwrite this method to get specific metadata.
     *
     * @return Array
     */
    protected function getMetadata()
    {
        return $this->em->getMetadataFactory()->getAllMetadata();
    }

    public function tearDown()
    {
        // Shutdown the kernel.
        $this->kernel->shutdown();

        parent::tearDown();
    }
}
