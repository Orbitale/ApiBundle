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

use Orbitale\Bundle\ApiBundle\Tests\Fixtures\ApiDataTestBundle\Entity\ApiData;
use Orbitale\Bundle\ApiBundle\Tests\Fixtures\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends WebTestCase
{

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

        $datas = $this->getResponseContent($response, 500);
        $expectedExceptionMessage = sprintf($expectedExceptionMessage, $wrongService);
        $this->assertEquals(true, @$datas['error']);
        $this->assertEquals($expectedExceptionMessage, @$datas['message']);
    }

    public function provideWrongServices()
    {
        return array(
            array(true, 'Service "%s" not found in the API.'."\n".'Did you forget to specify it in your configuration ?'."\n".'Available services : data'),
            array(false, 'Unrecognized service %s'),
        );
    }

    public function testCget()
    {
        $fixtures = $this->generateFixtures();

        $datas = $this->sendRequest('data');

        $this->assertCount(count($fixtures), isset($datas['data']) ? $datas['data'] : array());
        foreach ($datas['data'] as $data) {
            $this->assertArrayHasKey($data['id'], $fixtures);
            $fixture = $fixtures[$data['id']];
            $this->assertEquals(@$data['name'], $fixture->getName());
        }
    }

    /**
     * @dataProvider getExpectedFixturesIds
     */
    public function testIdGet($id)
    {
        $fixture = static::$entityFixtures[$id];

        $content = $this->sendRequest('data/'.$id);

        $this->assertArrayHasKey('data', $content);
        // Count number of fields in the resource
        $this->assertCount(4, isset($content['data']) ? $content['data'] : array());
        $data = isset($content['data']) ? $content['data'] : array();

        $this->assertEquals($id, $data['id']);
        $this->assertEquals($fixture->getName(), $data['name']);
        $this->assertEquals($fixture->getValue(), $data['value']);
        $this->assertArrayNotHasKey('hidden', $data);
    }


    public function testIdGetWithInexistentItem()
    {
        $data = $this->sendRequest('data/654650687', 'GET', array(), 404);

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertContains('No item found', isset($data['message']) ? $data['message'] : null);
    }

    /**
     * Checks that the router is correctly set up to accept only numeric ids
     *
     * @dataProvider provideWrongIds
     */
    public function testIdGetWithWrongIds($id)
    {

        $client = static::createClient();

        $client->request('GET', '/api/data/'.$id);

        $response = $client->getResponse();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertNotInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function provideWrongIds()
    {
        return array(
            array('wrong'),
            array(-1),
            array('another_inexistent_element'),
        );
    }

    /**
     * @dataProvider getExpectedFixturesIds
     */
    public function testSubElementSimpleAttributeGet($id)
    {
        $fixture = static::$entityFixtures[$id];

        $content = $this->sendRequest('data/'.$id.'/value');

        $this->assertArrayHasKey('data', $content);
        $this->assertArrayHasKey('path', $content);
        $this->assertEquals('data.'.$id.'.value', $content['path']);
        $data = $content['data'];
        $this->assertNotNull($data);
        $this->assertEquals($fixture->getValue(), $data);
    }

    public function testPost()
    {
        $baseObject = array(
            'json' => array('name' => 'new name', 'value' => 10, 'hidden' => 'will not be inserted (no mapping, so serializer only)',),
            'mapping' => null,
        );

        $content = $this->sendRequest('data', 'POST', $baseObject, 201);

        $this->assertArrayHasKey('data', $content);
        $this->assertArrayHasKey('path', $content);
        $this->assertArrayHasKey('link', $content);

        $data = $content['data'];

        $expectedFixtureIds = array_keys(static::$entityFixtures);
        $expectedId = max($expectedFixtureIds) + 1;

        $this->assertEquals($expectedId, $data['id']);
        $this->assertEquals($baseObject['json']['name'], $data['name']);
        $this->assertEquals($baseObject['json']['value'], $data['value']);
        $this->assertArrayNotHasKey('hidden', $data);
        $this->assertEquals('data.'.$expectedId, $content['path']);

        /** @var ApiData $dbObject */
        $dbObject = $this->getKernel()->getContainer()->get('doctrine')->getManager()->getRepository($this->entityClass)->find($data['id']);

        $this->assertNotNull($dbObject);
        $this->assertInstanceOf($this->entityClass, $dbObject);
        $this->assertEquals(null, $dbObject->getHidden());

        $this->reloadFixtures();
    }

    public function testPostMapping()
    {
        $baseObject = array(
            'json' => array('name' => 'AnotherOne', 'value' => 50, 'hidden' => 'Correct!',),
            'mapping' => array('name' => true, 'value' => true, 'hidden' => true),
        );

        $content = $this->sendRequest('data', 'POST', $baseObject, 201);

        $this->assertArrayHasKey('data', $content);
        $this->assertArrayHasKey('path', $content);
        $this->assertArrayHasKey('link', $content);

        $data = $content['data'];

        $expectedFixtureIds = array_keys(static::$entityFixtures);
        $expectedId = max($expectedFixtureIds) + 1;

        $this->assertEquals($expectedId, $data['id']);
        $this->assertEquals($baseObject['json']['name'], $data['name']);
        $this->assertEquals($baseObject['json']['value'], $data['value']);
        $this->assertArrayNotHasKey('hidden', $data);
        $this->assertEquals('data.'.$expectedId, $content['path']);

        /** @var ApiData $dbObject */
        $dbObject = $this->getKernel()->getContainer()->get('doctrine')->getManager()->getRepository($this->entityClass)->find($data['id']);

        $this->assertNotNull($dbObject);
        $this->assertInstanceOf($this->entityClass, $dbObject);
        $this->assertEquals($baseObject['json']['hidden'], $dbObject->getHidden());

        $this->reloadFixtures();
    }

    public function testPostWithId()
    {
        $baseObject = array(
            'json' => array('id' => 1, 'name' => 'AnotherOne', 'value' => 50, 'hidden' => 'Correct!',),
            'mapping' => array('id' => true, 'name' => true, 'value' => true, 'hidden' => true),
        );

        $content = $this->sendRequest('data', 'POST', $baseObject, 500);

        $this->assertArrayHasKey('error', $content);
        $this->assertArrayHasKey('message', $content);

        $this->assertContains('"POST" method is used to insert new datas. If you want to edit an object, use the "PUT" method instead.', $content['message']);
    }

    public function testPostWithWrongDatas()
    {
        $baseObject = array(
            'json' => array('name' => '', 'value' => 0),
            'mapping' => array('name' => true, 'value' => true),
        );
        $content = $this->sendRequest('data', 'POST', $baseObject, 400);

        $this->assertArrayHasKey('error', $content);
        $this->assertArrayHasKey('message', $content);

        $this->assertContains('Invalid form, please re-check.', $content['message']);

        $errors = isset($content['errors']) ? $content['errors'] : array();

        $this->assertCount(1, $errors);

        $error = current($errors);

        $this->assertEquals('name', $error['property_path']);
        $translatedErrorMessage = $this->getKernel()->getContainer()->get('translator')->trans('This value should not be blank.', array(), 'validators');
        $this->assertEquals($translatedErrorMessage, @$error['message']);
    }

    /**
     * Sends a request to the controller and get its response content.
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

        $datas = $this->getResponseContent($response, $expectedCode);
        if ($expectedCode >= 400) {
            $this->assertArrayHasKey('error', $datas);
        } else {
            $this->assertArrayNotHasKey('error', $datas);
        }
        return $datas;
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

        $this->assertNotEmpty($json);

        $jsonLastErr = json_last_error();
        $jsonMsg = $this->parseJsonMsg($jsonLastErr);

        $this->assertEquals(JSON_ERROR_NONE, $jsonLastErr, "\nERROR! Invalid response, json error:\n> ".$jsonLastErr.$jsonMsg);

        return $json;
    }
}
