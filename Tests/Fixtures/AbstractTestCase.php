<?php
/*
 * This file is part of the OrbitaleApiBundle package.
 *
 * (c) Alexandre Rock Ancelet <contact@orbitale.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Orbitale\Bundle\ApiBundle\Tests\Fixtures;

use AppKernel;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractTestCase extends WebTestCase
{

    const ENTITY_CLASS = 'Orbitale\Bundle\ApiBundle\Tests\Fixtures\ApiDataTestBundle\Entity\ApiData';

    /**
     * @var EntityManager
     */
    protected static $em;

    /**
     * @var Container
     */
    protected static $container;

    /**
     * @var AppKernel
     */
    protected static $kernel;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->initKernelAndController('test');
    }

    /**
     * @param array $options
     */
    protected static function bootKernel(array $options = array())
    {
        if (method_exists('Symfony\Bundle\FrameworkBundle\Test\KernelTestCase', 'bootKernel')) {
            parent::bootKernel($options);
        } else {
            if (null !== static::$kernel) {
                static::$kernel->shutdown();
            }
            static::$kernel = static::createKernel($options);
            static::$kernel->boot();
        }
    }

    /**
     * @param array $options An array of options to pass to the createKernel class
     *
     * @return KernelInterface
     */
    protected static function getKernel(array $options = array())
    {
        static::bootKernel($options);

        return static::$kernel;
    }

    /**
     * Manually set up kernel and generate schema/fixtures.
     *
     * @param string $env
     *
     * @throws SchemaException
     */
    protected function initKernelAndController($env)
    {
        if (static::$kernel) {
            $this->tearDown();
        }

        // Boot the AppKernel in the test environment and with the debug.
        static::$kernel = static::getKernel(['environment' => $env, 'debug' => true]);

        // Store the container and the entity manager in test case properties
        static::$container  = static::$kernel->getContainer();
        static::$em         = static::$container->get('doctrine')->getManager();
    }

    public static function createRequest($method = 'GET', $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = null)
    {
        $server = array_replace(array(
            'HTTP_ORIGIN' => 'http://localhost/',
        ), $server);

        $httpRequest = Request::create('/', $method, $parameters, $cookies, $files, $server, $content);

        $httpRequest->attributes->set('_controller', 'Orbitale\Bundle\ApiBundle\Controller\ApiController');

        return $httpRequest;
    }

    /**
     * Polyfill in case json_last_error_msg() is not available.
     *
     * @param int $jsonLastErr
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

    /**
     * Overwrite this method to get specific metadata.
     *
     * @return array
     */
    protected function getMetadata()
    {
        return static::$em->getMetadataFactory()->getAllMetadata();
    }

    public function tearDown()
    {
        if (static::$kernel) {
            // Shutdown the kernel.
            static::$kernel->shutdown();
            static::$kernel = null;
        }

        parent::tearDown();
    }
}
