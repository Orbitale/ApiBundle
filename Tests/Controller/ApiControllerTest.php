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
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends AbstractTestCase
{

    /**
     * @param Response $response
     * @param int      $expectedCode
     *
     * @return array
     */
    protected function getResponseContent(Response $response, $expectedCode = 200)
    {
        $this->assertEquals($expectedCode, $response->getStatusCode());
        $this->assertContains('application/json', $response->headers->get('Content-type'));
        $this->assertNotEmpty($response->getContent());

        $json = json_decode($response->getContent(), true);

        $jsonLastErr = json_last_error();
        $jsonMsg = $this->parseJsonMsg($jsonLastErr);

        $this->assertEquals(JSON_ERROR_NONE, $jsonLastErr, "\nERROR! Invalid response, json error:\n> ".$jsonLastErr.$jsonMsg);

        return $json;
    }

    public function testDbNotEmpty()
    {
        $objects = $this->em->getRepository($this->entityClass)->findAll();
        $this->assertNotEmpty($objects);
    }

    public function testTwiceTheService()
    {
        $this->controller->cgetAction('data', $this->createRequest());

        $service = $this->controller->getService();

        $this->assertTrue(is_array($service));
        $this->assertNotEmpty($service);
    }

    /**
     * @dataProvider provideWrongServices
     */
    public function testWrongService($env, $expectedExceptionMessage)
    {
        $this->initKernelAndController($env);

        $wrongService = 'wrong_service';

        $exception = new \Exception();

        try {
            $this->controller->cgetAction($wrongService, $this->createRequest());
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->assertContains(sprintf($expectedExceptionMessage, $wrongService), $exception->getMessage());
        $this->assertContains('ApiController', $exception->getFile());
    }

    public function provideWrongServices()
    {
        return array(
            array('test', 'Service "%s" not found in the API.'),
            array('prod', 'Unrecognized service %s'),
        );
    }

    public function testCget()
    {
        $response = $this->controller->cgetAction('data', $this->createRequest());

        $ids = $this->getExpectedFixturesIds();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);

        if ($response instanceof Response) {
            $content = $this->getResponseContent($response);
            $this->assertArrayHasKey('data', $content);
            $this->assertCount(count($this->entityFixtures), isset($content['data']) ? $content['data'] : array());
            foreach ($ids as $id) {
                $id = current($id) - 1;
                $data = array_key_exists($id, $content['data']) ? $content['data'][$id] : null;
                $this->assertNotNull($data);
                if (null !== $data) {
                    $this->assertEquals($data['name'], $this->entityFixtures[$id]['name']);
                    $this->assertEquals($data['value'], $this->entityFixtures[$id]['value']);
                    $this->assertArrayHasKey('id', $data);
                    $this->assertArrayNotHasKey('hidden', $data);
                }
            }
        }
    }

    public function getExpectedFixturesIds()
    {
        $ids = array();

        foreach ($this->entityFixtures as $key => $fixture) {
            $ids[] = array($key + 1);
        }

        return $ids;
    }

    /**
     * @dataProvider getExpectedFixturesIds
     */
    public function testIdGet($id)
    {
        $response = $this->controller->getAction('data', $id, null, $this->createRequest());

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);

        if ($response instanceof Response) {
            $content = $this->getResponseContent($response);
            $this->assertArrayHasKey('data', $content);
            $this->assertCount(4, isset($content['data']) ? $content['data'] : array());
            $data = isset($content['data']) ? $content['data'] : array();
            if (count($data)) {
                $this->assertEquals($id, $data['id']);
                $this->assertEquals($this->entityFixtures[$id-1]['name'], $data['name']);
                $this->assertEquals($this->entityFixtures[$id-1]['value'], $data['value']);
                $this->assertArrayNotHasKey('hidden', $data);
            }
        }
    }

    /**
     * @dataProvider getExpectedFixturesIds
     */
    public function testSubElementSimpleAttributeGet($id)
    {
        $response = $this->controller->getAction('data', $id, 'value', $this->createRequest());

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);

        if ($response instanceof Response) {
            $content = $this->getResponseContent($response);
            $this->assertArrayHasKey('data', $content);
            $this->assertArrayHasKey('path', $content);
            $this->assertEquals('data.'.$id.'.value', isset($content['path']) ? $content['path'] : null);
            $data = isset($content['data']) ? $content['data'] : null;
            $this->assertNotNull($data);
            $this->assertEquals($this->entityFixtures[$id-1]['value'], $data);
        }
    }

}
