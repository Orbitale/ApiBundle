<?php
/*
* This file is part of the OrbitaleApiBundle package.
*
* (c) Alexandre Rock Ancelet <contact@orbitale.io>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Orbitale\Bundle\ApiBundle\Tests\Controller;

use Orbitale\Bundle\ApiBundle\Tests\Fixtures\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends WebTestCase
{

    /**
     * Sends a request to the controller and get its response content.
     * Some asserts are made here because we only check for successful requests here.
     *
     * @param string  $uri
     * @param string  $method
     * @param array   $parameters
     * @param integer $expectedCode
     *
     * @return array
     */
    protected function sendRequest($uri, $method = 'GET', array $parameters = array(), $expectedCode = 200)
    {
        $client = static::createClient();

        $client->request($method, '/api/'.$uri, $parameters, array(), array('HTTP_ACCEPT' => 'application/json'));

        $response = $client->getResponse();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);

        if ($response instanceof JsonResponse) {
            $datas = $this->getResponseContent($response, $expectedCode);
            $this->assertArrayNotHasKey('error', $datas);
            return $datas;
        }

        throw new \RuntimeException('Unknown error in sending request.');
    }

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

        $json = json_decode($response->getContent(), true);

        dump($response);

        $this->assertNotEmpty($json);

        $jsonLastErr = json_last_error();
        $jsonMsg = $this->parseJsonMsg($jsonLastErr);

        $this->assertEquals(JSON_ERROR_NONE, $jsonLastErr, "\nERROR! Invalid response, json error:\n> ".$jsonLastErr.$jsonMsg);

        return $json;
    }

    public function testDbEmpty()
    {
        $objects = $this->getEm()->getRepository($this->entityClass)->findAll();
        $this->assertEmpty($objects);

        $this->getFixtures();
    }

    /**
     * Test that the controller throws a proper exception message depending on the `kernel.debug` parameter.
     *
     * @dataProvider provideWrongServices
     */
    public function testWrongService($debug, $expectedExceptionMessage)
    {
        $wrongService = 'wrong_service';

        $client = static::createClient(array('debug' => $debug));

        $client->request('GET', '/api/'.$wrongService, array(), array(), array('HTTP_ACCEPT' => 'application/json'));

        $response = $client->getResponse();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);

        if ($response instanceof JsonResponse) {
            $datas = $this->getResponseContent($response, 500);
            $expectedExceptionMessage = sprintf($expectedExceptionMessage, $wrongService);
            $this->assertEquals(true, @$datas['error']);
            $this->assertEquals($expectedExceptionMessage, @$datas['message']);
        }
    }

    public function provideWrongServices()
    {
        return array(
            array(true, 'Service "%s" not found in the API.'."\n".'Did you forget to specify it in your configuration ?'."\n".'Available services : data'),
            array(false, 'Unrecognized service %s'),
        );
    }

    public function atestCget()
    {
        $datas = $this->sendRequest('data');

        $fixtures = $this->getFixtures();

        $this->assertCount(count($fixtures), isset($content['data']) ? $content['data'] : array());
        foreach ($datas as $data) {
            $this->assertArrayHasKey($data['id'], $fixtures);
            $fixture = $fixtures[$data['id']];
            $this->assertEquals(@$data['name'], $fixture->getName());
        }
    }

    /**
     * @dataProvider getExpectedFixturesIds
     */
    public function atestIdGet($id)
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
     * @dataProvider provideWrongIds
     */
    public function atestIdGetWithInexistentItem($id)
    {

        $response = $this->controller->getAction('data', $id, null, $this->createRequest());

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);

        if ($response instanceof Response) {
            $data = $this->getResponseContent($response, 404);
            $this->assertArrayHasKey('error', $data);
            $this->assertArrayHasKey('message', $data);
            $this->assertContains('No item found', isset($data['message']) ? $data['message'] : null);
        }
    }

    public function provideWrongIds()
    {
        return array(
            array(1000),
            array('wrong'),
            array(-1),
        );
    }

    /**
     * @dataProvider getExpectedFixturesIds
     */
    public function atestSubElementSimpleAttributeGet($id)
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

    public function atestPost()
    {
        $data = array(
            'json' => array('name' => 'new name', 'value' => 10, 'hidden' => 'will not be inserted (no mapping, so serializer only)',),
            'mapping' => null,
        );
        $request = $this->createRequest('POST', $data);

        $response = $this->controller->postAction('data', $request);

        $content = $this->getResponseContent($response, 201);

        $this->assertArrayHasKey('data', $content);
        $this->assertArrayHasKey('path', $content);
        $this->assertArrayHasKey('link', $content);

        $data = @$content['data'];

        $this->assertEquals(4, @$data['id']);
        $this->assertEquals('new name', @$data['name']);
        $this->assertEquals(10, @$data['value']);
        $this->assertArrayNotHasKey('hidden', $data);
        $this->assertEquals('data.4', $content['path']);

        $dbObject = $this->container->get('doctrine')->getManager()->getRepository($this->entityClass)->find($data['id']);

        $this->assertNotNull($dbObject);
        if (null !== $dbObject) {
            $this->assertEquals(null, $dbObject->getHidden());
        }
    }

    public function atestPostMapping()
    {
        $data = array(
            'json' => array('name' => 'AnotherOne', 'value' => 50, 'hidden' => 'Correct!',),
            'mapping' => array('name' => true, 'value' => true, 'hidden' => true),
        );
        $request = $this->createRequest('POST', $data);

        $response = $this->controller->postAction('data', $request);

        $content = $this->getResponseContent($response, 201);

        $this->assertArrayHasKey('data', $content);
        $this->assertArrayHasKey('path', $content);
        $this->assertArrayHasKey('link', $content);

        $data = @$content['data'];

        $this->assertEquals(4, @$data['id']);
        $this->assertEquals('AnotherOne', @$data['name']);
        $this->assertEquals(50, @$data['value']);
        $this->assertArrayNotHasKey('hidden', $data);
        $this->assertEquals('data.4', $content['path']);

        $dbObject = $this->container->get('doctrine')->getManager()->getRepository($this->entityClass)->find($data['id']);

        $this->assertNotNull($dbObject);
        if (null !== $dbObject) {
            $this->assertEquals('Correct!', $dbObject->getHidden());
        }
    }

    public function atestPostWithId()
    {
        $data = array(
            'json' => array('id' => 1, 'name' => 'AnotherOne', 'value' => 50, 'hidden' => 'Correct!',),
            'mapping' => array('id' => true, 'name' => true, 'value' => true, 'hidden' => true),
        );
        $request = $this->createRequest('POST', $data);

        $response = null;
        $e = null;
        try {
            $response = $this->controller->postAction('data', $request);
        } catch (\Exception $e) {
            $this->assertContains('"POST" method is used to insert new datas.', $e->getMessage());
            $this->assertContains('If you want to edit an object, use the "PUT" method instead.', $e->getMessage());
        }

        $this->assertNull($response);
        $this->assertInstanceOf('InvalidArgumentException', $e);
        $this->assertInstanceOf('Exception', $e);
    }

    public function atestPostWithWrongDatas()
    {
        $data = array(
            'json' => array('name' => '', 'value' => 0),
            'mapping' => array('name' => true, 'value' => true),
        );
        $request = $this->createRequest('POST', $data);

        $response = $this->controller->postAction('data', $request);

        $content = $this->getResponseContent($response, 400);

        $this->assertTrue(@$content['error']);
        $this->assertEquals('Invalid form, please re-check.', @$content['message']);

        $errors = isset($content['errors']) ? $content['errors'] : array();
        $this->assertEquals(1, count($errors));

        $error = current($errors);

        $this->assertEquals('name', @$error['property_path']);
        $translatedErrorMessage = $this->container->get('translator')->trans('This value should not be blank.', array(), 'validators');
        $this->assertEquals($translatedErrorMessage, @$error['message']);
    }

}
