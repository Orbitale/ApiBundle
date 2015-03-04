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
     * @return array
     */
    protected function getResponseContent(Response $response, $expectedCode = 200)
    {
        $this->assertEquals($expectedCode, $response->getStatusCode());
        $this->assertContains('application/json', $response->headers->get('Content-type'));
        $this->assertNotEmpty($response->getContent());

        $json = json_decode($response->getContent(), true);

        $jsonLastErr = json_last_error();
        switch ($jsonLastErr) {
            case JSON_ERROR_NONE:           $jsonMsg = ' - No errors'; break;
            case JSON_ERROR_DEPTH:          $jsonMsg = ' - Maximum stack depth exceeded'; break;
            case JSON_ERROR_STATE_MISMATCH: $jsonMsg = ' - Underflow or the modes mismatch'; break;
            case JSON_ERROR_CTRL_CHAR:      $jsonMsg = ' - Unexpected control character found'; break;
            case JSON_ERROR_SYNTAX:         $jsonMsg = ' - Syntax error, malformed JSON'; break;
            case JSON_ERROR_UTF8:           $jsonMsg = ' - Malformed UTF-8 characters, possibly incorrectly encoded'; break;
            default:                        $jsonMsg = ' - Unknown error'; break;
        }
        $this->assertEquals(JSON_ERROR_NONE, $jsonLastErr, "\nERROR! Invalid response, json error:\n> ".$jsonLastErr.$jsonMsg);

        return $json;
    }

    public function testDbNotEmpty()
    {
        $objects = $this->em->getRepository($this->entityClass)->findAll();
        $this->assertNotEmpty($objects);
    }

    public function testCget()
    {
        $response = $this->controller->cgetAction('data', $this->createRequest());

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);

        if ($response instanceof Response) {
            $content = $this->getResponseContent($response);
            $this->assertArrayHasKey('data', $content);
            $this->assertCount(count($this->entityFixtures), isset($content['data']) ? $content['data'] : array());
        }

    }
}
