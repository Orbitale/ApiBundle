<?php
/*
* This file is part of the OrbitaleApiBundle package.
*
* (c) Alexandre Rock Ancelet <contact@orbitale.io>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Orbitale\Bundle\ApiBundle\Tests\Listeners;

use Orbitale\Bundle\ApiBundle\Listeners\JsonResponseListener;
use Orbitale\Bundle\ApiBundle\Tests\Fixtures\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class JsonResponseListenerTest extends WebTestCase
{

    public function provideExceptionEnvs()
    {
        return array(
            array('Test exception', false, 666),
            array('Dev exception', true, -1),
        );
    }

    /**
     * @dataProvider provideExceptionEnvs
     */
    public function testKernelEventException($exceptionMessage, $debug, $exceptionCode)
    {
        $kernel = $this->createKernel(array('debug' => $debug));
        $kernel->boot();

        $listener = new JsonResponseListener($debug);

        $exceptionEvent = new GetResponseForExceptionEvent($kernel, $this->createRequest('GET'), HttpKernelInterface::MASTER_REQUEST, new \Exception($exceptionMessage, $exceptionCode));

        $listener->onException($exceptionEvent);

        $response = $exceptionEvent->getResponse();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);

        if ($response instanceof JsonResponse) {
            $content     = json_decode($response->getContent(), true);
            $jsonLastErr = json_last_error();
            $jsonMsg     = $this->parseJsonMsg($jsonLastErr);

            $this->assertEquals(JSON_ERROR_NONE, $jsonLastErr, "\nERROR! Invalid response, json error:\n> ".$jsonLastErr.$jsonMsg);
            if (null !== $content) {
                $this->assertArrayHasKey('error', $content);
                $this->assertArrayHasKey('message', $content);
                $this->assertArrayHasKey('exception', $content);
                $this->assertEquals(true, isset($content['error']) ? $content['error'] : false);
                $this->assertEquals($exceptionMessage, isset($content['message']) ? $content['message'] : null);
                $this->assertEquals($exceptionCode, isset($content['exception']['code']) ? $content['exception']['code'] : null);
                if ($debug) {
                    $this->assertArrayHasKey('exception_trace', $content);
                    $this->assertGreaterThan(0, count(isset($content['exception_trace']) ? $content['exception_trace'] : array()));
                } else {
                    $this->assertArrayNotHasKey('exception_trace', $content);
                }
            }
        }
    }

    public function testKernelEventResponse()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $listener = new JsonResponseListener('test');

        $response = new Response();

        $responseEvent = new FilterResponseEvent($kernel, $this->createRequest('GET'), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener->onResponse($responseEvent);

        $this->assertEquals('application/json', $response->headers->get('Content-type'));
    }

    public function testEventsList()
    {
        $listener = new JsonResponseListener('test');

        $list = $listener->getSubscribedEvents();

        $this->assertCount(2, $list);
    }

    /**
     * Creates a HTTP Request object to be checked in the API
     *
     * @param string $method     The HTTP method
     * @param array  $parameters The query (GET) or request (POST) parameters
     * @param array  $cookies    The request cookies ($_COOKIE)
     * @param array  $files      The request files ($_FILES)
     * @param array  $server     The server parameters ($_SERVER)
     * @param string $content    The raw body data
     *
     * @see Request
     *
     * @return Request
     */
    public function createRequest($method = 'GET', $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = null)
    {
        $this->getContainer()->enterScope('request');

        $server = array_replace(array(
            'HTTP_ORIGIN' => 'http://localhost/',
        ), $server);

        $httpRequest = Request::create('/', $method, $parameters, $cookies, $files, $server, $content);

        $httpRequest->attributes->set('_controller', 'Orbitale\Bundle\ApiBundle\Controller\ApiController');

        $this->getContainer()->set('request', $httpRequest, 'request');

        return $httpRequest;
    }
}
