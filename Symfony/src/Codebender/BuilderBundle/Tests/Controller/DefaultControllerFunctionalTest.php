<?php
/**
 * Created by PhpStorm.
 * User: fpapadopou
 * Date: 4/20/15
 * Time: 12:07 PM
 */

namespace Codebender\BuilderBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

class DefaultControllerFunctionalTest extends WebTestCase
{
    public function testStatusAction() {
        $client = static::createClient();

        $client->request('GET', '/status');

        $this->assertEquals($client->getResponse()->getContent(), '{"success":true,"status":"OK"}');
    }

    public function testHandleRequestErrors() {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $apiVersion = $client->getContainer()->getParameter('version');
        $client->request('GET', "/{$authorizationKey}/{$apiVersion}/");

        $this->assertEquals($client->getResponse()->getStatusCode(), 405);

        // insufficient data
        $content = json_encode(['type' => 'compiler']);
        $response = $this->performPostRequest($content);
        $this->assertFalse($response['success']);
        $this->assertEquals('Insufficient data provided.', $response['message']);

        // invalid action requested
        $content = json_encode(['type' => 'helloooo', 'data' => []]);
        $response = $this->performPostRequest($content);
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid request type (can handle only \'compiler\' or \'library\' requests)',
            $response['message']);
    }

    public function testHandleRequestCompile() {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $apiVersion = $client->getContainer()->getParameter('version');
        $client
            ->request(
                'POST',
                "/{$authorizationKey}/{$apiVersion}/",
                $parameters = [],
                $files = [],
                $server = [],
                $content = '{"type":"compiler","data":{"files":[{"filename":"project.ino","content":"void setup(){\n\n}\nvoid loop(){\n\n}\n"}],"format":"binary","version":"105","build":{"mcu":"atmega328p","f_cpu":"16000000L","core":"arduino","variant":"standard"}}}',
                $changeHistory = true);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $response);
        $this->assertEquals($response['success'], true);
        $this->assertArrayHasKey('time', $response);
    }

    public function testHandleRequestLibraryFetching() {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $apiVersion = $client->getContainer()->getParameter('version');
        $client
            ->request(
                'POST',
                "/{$authorizationKey}/{$apiVersion}/",
                $parameters = [],
                $files = [],
                $server = [],
                $content = '{"type":"library","data":{"type":"fetch","library":"Ethernet"}}',
                $changeHistory = true);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $response);
        $this->assertEquals($response['success'], true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals($response['message'], 'Library found');
    }

    public function testHandleRequestLibraryKeywords() {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $apiVersion = $client->getContainer()->getParameter('version');
        $client
            ->request(
                'POST',
                "/{$authorizationKey}/{$apiVersion}/",
                $parameters = [],
                $files = [],
                $server = [],
                $content = '{"type":"library","data":{"type":"getKeywords","library":"Ethernet"}}',
                $changeHistory = true);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $response);
        $this->assertEquals($response['success'], true);
        $this->assertArrayHasKey('keywords', $response);
        $this->assertTrue(is_array($response['keywords']));
    }

    public function testGeneratePayloadAction()
    {
        $providedContent = '{"files":[{"filename":"project.ino","content":"void setup(){\n\n}\nvoid loop(){\n\n}\n"}],"format":"binary","version":"105","build":{"mcu":"atmega328p","f_cpu":"16000000L","core":"arduino","variant":"standard"}}';
        $response = $this->performPostRequest($providedContent, 'payload');
        $this->assertEquals(
            ['userId', 'projectId', 'files', 'format', 'version', 'build', 'libraries', 'success', 'additionalCode'],
            array_keys($response)
        );
    }

    /**
     * Performs a POST
     * @param string $requestContent JSON-encoded POST request content
     * @param string $uri
     * @return array
     */
    protected function performPostRequest($requestContent, $uri = '')
    {
        $client = static::createClient();
        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');
        $apiVersion = $client->getContainer()->getParameter('version');
        $client
            ->request(
                'POST',
                "/{$authorizationKey}/{$apiVersion}/" . $uri,
                $parameters = [],
                $files = [],
                $server = [],
                $content = $requestContent,
                $changeHistory = true);

        return json_decode($client->getResponse()->getContent(), true);
    }
}
