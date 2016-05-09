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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
                static::assertArrayHasKey('info', $content);
                static::assertArrayHasKey('error_code', $content);
                static::assertEquals(true, isset($content['error']) ? $content['error'] : false);
                static::assertEquals($exception->getMessage(), isset($content['info']) ? $content['info'] : null);
                static::assertEquals(array(), isset($content['data']) ? $content['data'] : null);

                if ($exception instanceof HttpException) {
                    static::assertEquals($exception->getStatusCode(), $response->getStatusCode());
                } else {
                    static::assertEquals(500, $response->getStatusCode());
                }
            }
        }
    }

    /**
     * @return array[]
     */
    public function provideExceptionEnvs()
    {
        return array(
            array(new \Exception('Test exception', 666)),
            array(new NotFoundHttpException('Test HTTP exception')),
        );
    }

    public function testEventsList()
    {
        static::assertCount(2, JsonResponseListener::getSubscribedEvents());
    }

}
