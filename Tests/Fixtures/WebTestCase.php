<?php

namespace Orbitale\Bundle\ApiBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Orbitale\Bundle\ApiBundle\Tests\Fixtures\ApiDataTestBundle\Entity\ApiData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class WebTestCase extends BaseWebTestCase
{

    /**
     * @var array
     */
    protected static $arrayFixtures = array();

    /**
     * @var ApiData[]
     */
    protected static $entityFixtures = array();

    /**
     * @var string
     */
    protected $entityClass = 'Orbitale\Bundle\ApiBundle\Tests\Fixtures\ApiDataTestBundle\Entity\ApiData';

    public function tearDown()
    {
        if (static::$kernel) {
            static::$kernel->shutdown();
            static::$kernel = null;
        }
        parent::tearDown();
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->getKernel()->getContainer();
    }

    /**
     * @return EntityManager
     */
    public function getEm()
    {
        return $this->getKernel()->getContainer()->get('doctrine')->getManager();
    }

    /**
     * @param array $options
     *
     * @return KernelInterface
     */
    public static function getKernel(array $options = array())
    {
        if (static::$kernel) {
            return static::$kernel;
        }
        static::$kernel = parent::createKernel($options);
        static::$kernel->boot();
        return static::$kernel;
    }

    /**
     * {@inheritdoc}
     */
    protected static function createClient(array $options = array(), array $server = array())
    {
        $kernel = static::getKernel($options);

        /**
         * @var Client $client
         */
        $client = $kernel->getContainer()->get('test.client');

        // Force restart to avoid history conflicts
        $client->restart();

        $server = array_replace(array(
            'HTTP_ORIGIN' => 'http://localhost/',
        ), $server);


        $client->setServerParameters($server);

        return $client;
    }

    /**
     * Generates fixtures to be tested in the different test cases.
     *
     * @return array
     */
    protected function generateFixtures()
    {
        if (count(static::$entityFixtures)) {
            return static::$entityFixtures;
        }

        // Generates a very long random string to check that webservices do not fail
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        $length = pow(10, 3);
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        static::$arrayFixtures = array(
            array('name' => 'First one',  'value' => 1,       'hidden' => 'this text should be hidden'),
            array('name' => 'Second one', 'value' => -1,      'hidden' => 'this text should also be hidden'),
            array('name' => 'Second one', 'value' => $length, 'hidden' => 'And another long text (care, it\'s long):'.$randomString),
        );
        $this->persistFixtures();
        return static::$entityFixtures;
    }

    /**
     * Persist the fixtures in the database to allow retrieving objects from the webservices
     */
    private function persistFixtures()
    {
        $kernel = static::$kernel;

        if (!$kernel || !static::$arrayFixtures) {
            return;
        }

        /** @var EntityManager $em */
        $em = $kernel->getContainer()->get('doctrine')->getManager();

        foreach (static::$arrayFixtures as $entity) {
            $object = new ApiData();
            $object
                ->setName($entity['name'])
                ->setValue($entity['value'])
                ->setHidden($entity['hidden'])
            ;
            $em->persist($object);
        }
        $em->flush();

        $repo = $em->getRepository('Orbitale\Bundle\ApiBundle\Tests\Fixtures\ApiDataTestBundle\Entity\ApiData');

        static::$entityFixtures = $repo->findAll();
    }

    /**
     * Converts a JSON_ERROR_* code into the associated message.
     * Mostly for PHP5.3 and PHP5.4, in which the `json_last_error_msg()` does not exist.
     *
     * @param $jsonLastErr
     *
     * @return string
     */
    protected function parseJsonMsg($jsonLastErr)
    {
        switch ($jsonLastErr) {
            case JSON_ERROR_NONE:           $jsonMsg = ' - No errors'; break;
            case JSON_ERROR_DEPTH:          $jsonMsg = ' - Maximum stack depth exceeded'; break;
            case JSON_ERROR_STATE_MISMATCH: $jsonMsg = ' - Underflow or the modes mismatch'; break;
            case JSON_ERROR_CTRL_CHAR:      $jsonMsg = ' - Unexpected control character found'; break;
            case JSON_ERROR_SYNTAX:         $jsonMsg = ' - Syntax error, malformed JSON'; break;
            case JSON_ERROR_UTF8:           $jsonMsg = ' - Malformed UTF-8 characters, possibly incorrectly encoded'; break;
            default:                        $jsonMsg = ' - Unknown error'; break;
        }
        return $jsonMsg;
    }

}
