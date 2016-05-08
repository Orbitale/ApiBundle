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
use Orbitale\Bundle\ApiBundle\Tests\Fixtures\AbstractTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class JsonResponseListenerTest extends AbstractTestCase
{

    public function testKernelEventResponse()
    {
        $listener = new JsonResponseListener('test');

        $response = new Response();

        $responseEvent = new FilterResponseEvent(static::getKernel(), static::createRequest('GET'), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener->onResponse($responseEvent);

        static::assertEquals('application/json', $response->headers->get('Content-type'));
    }

    /**
     * @dataProvider provideExceptionEnvs
     *
     * @param \Exception $exception
     */
    public function testKernelEventException(\Exception $exception)
    {
        $listener = new JsonResponseListener(true);

        $exceptionEvent = new GetResponseForExceptionEvent(static::getKernel(), static::createRequest('GET'), HttpKernelInterface::MASTER_REQUEST, $exception);

        $listener->onException($exceptionEvent);

        $response = $exceptionEvent->getResponse();

        static::assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);

        // This "if" avoids having tons of errors when phpunit config does not stop on error
        if ($response instanceof JsonResponse) {
            $content     = json_decode($response->getContent(), true);
            $jsonLastErr = json_last_error();
            $jsonMsg     = $this->parseJsonMsg($jsonLastErr);

            static::assertEquals(JSON_ERROR_NONE,
                $jsonLastErr,
                "\nERROR! Invalid response, json error:\n> " . $jsonLastErr . $jsonMsg
            );

            if (null !== $content) {
                static::assertArrayHasKey('error', $content);
                static::assertArrayHasKey('message', $content);
                static::assertArrayHasKey('exception', $content);
                static::assertEquals(true, isset($content['error']) ? $content['error'] : false);
                static::assertEquals($exception->getMessage(), isset($content['message']) ? $content['message'] : null);
                static::assertEquals($exception->getCode(), isset($content['exception']['code']) ? $content['exception']['code'] : null);
                static::assertArrayHasKey('exception_trace', $content);
                static::assertGreaterThan(0, count(isset($content['exception_trace']) ? $content['exception_trace'] : []));
            }
        }
    }

    /**
     * @return array[]
     */
    public function provideExceptionEnvs()
    {
        return array(
            array('Test exception', 'test', 666),
            array('Dev exception', 'dev', -1),
        );
    }

    public function testEventsList()
    {
        static::assertCount(2, JsonResponseListener::getSubscribedEvents());
    }

}
