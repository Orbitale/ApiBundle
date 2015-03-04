<?php
/*
* This file is part of the PierstovalApiBundle package.
*
* (c) Alexandre "Pierstoval" Rock Ancelet <pierstoval@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Pierstoval\Bundle\ApiBundle\Tests\Listeners;

use Pierstoval\Bundle\ApiBundle\Listeners\JsonResponseListener;
use Pierstoval\Bundle\ApiBundle\Tests\Fixtures\AbstractTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class JsonResponseListenerTest extends AbstractTestCase {

    public function testKernelEventResponse()
    {
        $listener = new JsonResponseListener('test');

        $response = new Response();

        $responseEvent = new FilterResponseEvent($this->kernel, $this->createRequest('GET'), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener->onResponse($responseEvent);

        $this->assertEquals('application/json', $response->headers->get('Content-type'));
    }

    /**
     * @dataProvider provideExceptionEnvs
     */
    public function testKernelEventException($exceptionMessage, $env, $exceptionCode)
    {
        $listener = new JsonResponseListener($env);

        $exceptionEvent = new GetResponseForExceptionEvent($this->kernel, $this->createRequest('GET'), HttpKernelInterface::MASTER_REQUEST, new \Exception($exceptionMessage, $exceptionCode));

        $listener->onException($exceptionEvent);

        $response = $exceptionEvent->getResponse();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);

        if ($response instanceof JsonResponse) {
            $content = json_decode($response->getContent(), true);
            $jsonLastErr = json_last_error();
            $jsonMsg = $this->parseJsonMsg($jsonLastErr);

            $this->assertEquals(JSON_ERROR_NONE, $jsonLastErr, "\nERROR! Invalid response, json error:\n> ".$jsonLastErr.$jsonMsg);
            if (null !== $content) {
                $this->assertArrayHasKey('error', $content);
                $this->assertArrayHasKey('message', $content);
                $this->assertArrayHasKey('exception', $content);
                $this->assertEquals(true, isset($content['error']) ? $content['error'] : false);
                $this->assertEquals($exceptionMessage, isset($content['message']) ? $content['message'] : null);
                $this->assertEquals($exceptionCode, isset($content['exception']['code']) ? $content['exception']['code'] : null);
                if ($env === 'dev') {
                    $this->assertArrayHasKey('exception_trace', $content);
                    $this->assertGreaterThan(0, count(isset($content['exception_trace']) ? $content['exception_trace'] : array()));
                } else {
                    $this->assertArrayNotHasKey('exception_trace', $content);
                }
            }
        }
    }

    public function provideExceptionEnvs()
    {
        return array(
            array('Test exception', 'test', 666),
            array('Dev exception', 'dev', -1),
        );
    }

    public function testEventsList()
    {
        $listener = new JsonResponseListener('test');

        $list = $listener->getSubscribedEvents();

        $this->assertCount(2, $list);
    }

}
