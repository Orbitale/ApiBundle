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

use Orbitale\Bundle\ApiBundle\Tests\Fixtures\AbstractTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends AbstractTestCase
{

    /**
     * @var array
     */
    protected static $entityFixtures = array();

    /**
     * @param Response $response
     * @param int      $expectedCode
     *
     * @return array
     */
    protected function getResponseContent(Response $response, $expectedCode = 200)
    {
        static::assertEquals($expectedCode, $response->getStatusCode());
        static::assertContains('application/json', $response->headers->get('Content-type'));
        static::assertNotEmpty($response->getContent());

        $json = json_decode($response->getContent(), true);

        $jsonLastErr = json_last_error();
        $jsonMsg = $this->parseJsonMsg($jsonLastErr);

        static::assertEquals(JSON_ERROR_NONE, $jsonLastErr, "\nERROR! Invalid response, json error:\n> ".$jsonLastErr.$jsonMsg);

        return $json;
    }

    public function testDbNotEmpty()
    {
        $objects = static::getKernel()
            ->getContainer()
            ->get('doctrine')
            ->getManager()
            ->getRepository(static::ENTITY_CLASS)->findAll()
        ;

        static::assertNotEmpty($objects);
    }

    public function testWrongService()
    {
        $client = static::createClient();

        $client->request('GET', '/wrong_service');

        $response = $client->getResponse();
    }

    public function testCget()
    {
        /*
        $response = $this->controller->cgetAction('data', $this->createRequest());

        $ids = $this->getExpectedFixturesIds();

        static::assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);

        if ($response instanceof Response) {
            $content = $this->getResponseContent($response);
            static::assertArrayHasKey('data', $content);
            static::assertCount(count($this->entityFixtures), isset($content['data']) ? $content['data'] : array());
            foreach ($ids as $id) {
                $id = current($id) - 1;
                $data = array_key_exists($id, $content['data']) ? $content['data'][$id] : null;
                static::assertNotNull($data);
                if (null !== $data) {
                    static::assertEquals($data['name'], $this->entityFixtures[$id]['name']);
                    static::assertEquals($data['value'], $this->entityFixtures[$id]['value']);
                    static::assertArrayHasKey('id', $data);
                    static::assertArrayNotHasKey('hidden', $data);
                }
            }
        }
        */
    }

    public function getExpectedFixturesIds()
    {
        if (!static::$entityFixtures) {
            static::$entityFixtures = static::$kernel
                ->getContainer()
                ->get('doctrine')
                ->getManager()
                ->getRepository('ApiDataTestBundle:ApiData')
                ->createQueryBuilder('data')
                ->select('data.id')
                ->getQuery()->getArrayResult()
            ;
        }

        return static::$entityFixtures;
    }

    /**
     * @dataProvider getExpectedFixturesIds
     */
    public function testIdGet($id)
    {
        /*
        $response = $this->controller->getAction('data', $id, null, $this->createRequest());

        static::assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);

        if ($response instanceof Response) {
            $content = $this->getResponseContent($response);
            static::assertArrayHasKey('data', $content);
            static::assertCount(4, isset($content['data']) ? $content['data'] : array());
            $data = isset($content['data']) ? $content['data'] : array();
            if (count($data)) {
                static::assertEquals($id, $data['id']);
                static::assertEquals($this->entityFixtures[$id-1]['name'], $data['name']);
                static::assertEquals($this->entityFixtures[$id-1]['value'], $data['value']);
                static::assertArrayNotHasKey('hidden', $data);
            }
        }
        */
    }

    /**
     * @dataProvider provideWrongIds
     */
    public function testIdGetWithInexistentItem($id)
    {
        /*

        $response = $this->controller->getAction('data', $id, null, $this->createRequest());

        static::assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);

        if ($response instanceof Response) {
            $data = $this->getResponseContent($response, 404);
            static::assertArrayHasKey('error', $data);
            static::assertArrayHasKey('message', $data);
            static::assertContains('No item found', isset($data['message']) ? $data['message'] : null);
        }
        */
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
    public function testSubElementSimpleAttributeGet($id)
    {
        /*
        $response = $this->controller->getAction('data', $id, 'value', $this->createRequest());

        static::assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);

        if ($response instanceof Response) {
            $content = $this->getResponseContent($response);
            static::assertArrayHasKey('data', $content);
            static::assertArrayHasKey('path', $content);
            static::assertEquals('data.'.$id.'.value', isset($content['path']) ? $content['path'] : null);
            $data = isset($content['data']) ? $content['data'] : null;
            static::assertNotNull($data);
            static::assertEquals($this->entityFixtures[$id-1]['value'], $data);
        }
        */
    }

    public function testPost()
    {
        /*
        $data = array(
            'json' => array('name' => 'new name', 'value' => 10, 'hidden' => 'will not be inserted (no mapping, so serializer only)',),
            'mapping' => null,
        );
        $request = $this->createRequest('POST', $data);

        $response = $this->controller->postAction('data', $request);

        $content = $this->getResponseContent($response, 201);

        static::assertArrayHasKey('data', $content);
        static::assertArrayHasKey('path', $content);
        static::assertArrayHasKey('link', $content);

        $data = @$content['data'];

        static::assertEquals(4, @$data['id']);
        static::assertEquals('new name', @$data['name']);
        static::assertEquals(10, @$data['value']);
        static::assertArrayNotHasKey('hidden', $data);
        static::assertEquals('data.4', $content['path']);

        $dbObject = $this->container->get('doctrine')->getManager()->getRepository($this->entityClass)->find($data['id']);

        static::assertNotNull($dbObject);
        if (null !== $dbObject) {
            static::assertEquals(null, $dbObject->getHidden());
        }
        */
    }

    public function testPostMapping()
    {
        /*
        $data = array(
            'json' => array('name' => 'AnotherOne', 'value' => 50, 'hidden' => 'Correct!',),
            'mapping' => array('name' => true, 'value' => true, 'hidden' => true),
        );
        $request = $this->createRequest('POST', $data);

        $response = $this->controller->postAction('data', $request);

        $content = $this->getResponseContent($response, 201);

        static::assertArrayHasKey('data', $content);
        static::assertArrayHasKey('path', $content);
        static::assertArrayHasKey('link', $content);

        $data = @$content['data'];

        static::assertEquals(4, @$data['id']);
        static::assertEquals('AnotherOne', @$data['name']);
        static::assertEquals(50, @$data['value']);
        static::assertArrayNotHasKey('hidden', $data);
        static::assertEquals('data.4', $content['path']);

        $dbObject = $this->container->get('doctrine')->getManager()->getRepository($this->entityClass)->find($data['id']);

        static::assertNotNull($dbObject);
        if (null !== $dbObject) {
            static::assertEquals('Correct!', $dbObject->getHidden());
        }
        */
    }

    public function testPostWithId()
    {
        /*
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
            static::assertContains('"POST" method is used to insert new datas.', $e->getMessage());
            static::assertContains('If you want to edit an object, use the "PUT" method instead.', $e->getMessage());
        }

        static::assertNull($response);
        static::assertInstanceOf('InvalidArgumentException', $e);
        static::assertInstanceOf('Exception', $e);
        */
    }

    public function testPostWithWrongDatas()
    {
        /*
        $data = array(
            'json' => array('name' => '', 'value' => 0),
            'mapping' => array('name' => true, 'value' => true),
        );
        $request = $this->createRequest('POST', $data);

        $response = $this->controller->postAction('data', $request);

        $content = $this->getResponseContent($response, 400);

        static::assertTrue(@$content['error']);
        static::assertEquals('Invalid form, please re-check.', @$content['message']);

        $errors = isset($content['errors']) ? $content['errors'] : array();
        static::assertEquals(1, count($errors));

        $error = current($errors);

        static::assertEquals('name', @$error['property_path']);
        $translatedErrorMessage = $this->container->get('translator')->trans('This value should not be blank.', array(), 'validators');
        static::assertEquals($translatedErrorMessage, @$error['message']);
        */
    }

}
