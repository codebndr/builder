<?php

namespace Codebender\BuilderBundle\Tests\Controller;
use Codebender\BuilderBundle\Controller\DefaultController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use \ReflectionMethod;

class DefaultControllerUnitTest extends \PHPUnit_Framework_TestCase
{
    public function testStatusAction() {

        $this->setUpController($controller, $container, $request, $apiHandler);
        $this->assertEquals('{"success":true,"status":"OK"}', $controller->statusAction()->getContent());
    }

    public function testHandleRequestInvalidAuthKey() {

        $this->setUpController($controller, $container, $request, $apiHandler);

        $container->expects($this->once())->method('getParameter')->with('authorizationKey')
            ->willReturn('anAuthKey');

        $actionResponse = $controller->handleRequestAction('invalidAuthKey', 'v1');

        $this->assertContains('Invalid authorization key.', $actionResponse->getContent());
    }

    public function testHandleRequestInvalidApiVersion() {

        $this->setUpController($controller, $container, $request, $apiHandler);

        $container->expects($this->at(0))->method('getParameter')->with('authorizationKey')
            ->willReturn('anAuthKey');
        $container->expects($this->at(1))->method('getParameter')->with('version')
            ->willReturn('v2');

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('Invalid api version.', $actionResponse->getContent());
    }

    public function testHandleRequestEmptyRequest() {

        $this->setUpController($controller, $container, $request, $apiHandler);

        $container->expects($this->at(0))->method('getParameter')->with('authorizationKey')
            ->willReturn('anAuthKey');
        $container->expects($this->at(1))->method('getParameter')->with('version')
            ->willreturn('v1');

        $controller->expects($this->once())->method('getRequest')
            ->willReturn($request);
        $request->expects($this->once())->method('getContent')
            ->willReturn('');

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('Invalid input.', $actionResponse->getContent());
    }

    public function testHandleRequestInvalidJsonDecodesToFalse() {

        $this->setUpController($controller, $container, $request, $apiHandler);

        $container->expects($this->at(0))->method('getParameter')->with('authorizationKey')
            ->willReturn('anAuthKey');
        $container->expects($this->at(1))->method('getParameter')->with('version')
            ->willReturn('v1');

        $controller->expects($this->once())->method('getRequest')
            ->willReturn($request);
        $request->expects($this->once())->method('getContent')
            ->willReturn("notJson");

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('Wrong data.', $actionResponse->getContent());
    }

    public function testHandleRequestNoDataKeyInRequest() {

        $this->setUpController($controller, $container, $request, $apiHandler);

        $container->expects($this->at(0))->method('getParameter')->with('authorizationKey')
            ->willReturn('anAuthKey');
        $container->expects($this->at(1))->method('getParameter')->with('version')
            ->willReturn('v1');

        $controller->expects($this->once())->method('getRequest')
            ->willReturn($request);
        $request->expects($this->once())->method('getContent')
            ->willReturn('{"type":"compiler"}');

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('Insufficient data provided.', $actionResponse->getContent());
    }

    public function testHandleRequestCompileRequest() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(['getRequest', 'compile', 'getLibraryInfo'])
            ->getMock();

        $controller->setContainer($container);

        $container->expects($this->at(0))->method('getParameter')->with('authorizationKey')
            ->willReturn('anAuthKey');
        $container->expects($this->at(1))->method('getParameter')->with('version')
            ->willReturn('v1');

        $controller->expects($this->once())->method('getRequest')
            ->willReturn($request);
        $request->expects($this->once())->method('getContent')
            ->willReturn('{"type":"compiler","data":[]}');

        $controller->expects($this->once())->method('compile')->with([])
            ->willReturn('someValue');

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('someValue', $actionResponse->getContent());
    }

    public function testHandleRequestLibraryRequest() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(['getRequest', 'compile', 'getLibraryInfo'])
            ->getMock();

        $controller->setContainer($container);

        $container->expects($this->at(0))->method('getParameter')->with('authorizationKey')
            ->willReturn('anAuthKey');
        $container->expects($this->at(1))->method('getParameter')->with('version')
            ->willReturn('v1');

        $controller->expects($this->once())->method('getRequest')
            ->willReturn($request);
        $request->expects($this->once())->method('getContent')
            ->willReturn('{"type":"invalidType","data":[]}');

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('Invalid request type', $actionResponse->getContent());
    }

    public function testCompileNonJsonCompilerResponse() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'getRequest', 'addUserIdProjectIdIfNotInRequest', 'returnProvidedAndFetchedLibraries'])
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('compile');

        $controller->expects($this->at(0))->method('get')->with('codebender_builder.handler')
            ->willReturn($apiHandler);
        $controller->expects($this->at(1))->method('addUserIdProjectIdIfNotInRequest')->with(['files' => []])
            ->willReturn(['files' => []]);
        $controller->expects($this->at(2))->method('returnProvidedAndFetchedLibraries')->with([])
            ->willReturn(['libraries' => []]);

        $container->expects($this->once())->method('getParameter')->with('compiler')
            ->willReturn('http://compiler/url');
        $apiHandler->expects($this->once())->method('postRawData')
            ->with('http://compiler/url', '{"files":[],"libraries":[]}')->willReturn('nonJson');

        $functionResponse = $function->invoke($controller, ['files' => []]);

        $this->assertContains('Failed to get compiler response', $functionResponse);
    }

    public function testCompileFalseCompilationWithoutStepIncluded() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'getRequest', 'addUserIdProjectIdIfNotInRequest', 'returnProvidedAndFetchedLibraries'])
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('compile');

        $controller->expects($this->at(0))->method('get')->with('codebender_builder.handler')
            ->willReturn($apiHandler);
        $controller->expects($this->at(1))->method('addUserIdProjectIdIfNotInRequest')->with(['files' => []])
            ->willReturn(['files' => []]);
        $controller->expects($this->at(2))->method('returnProvidedAndFetchedLibraries')->with([])
            ->willReturn(['libraries' => []]);

        $container->expects($this->once())->method('getParameter')->with('compiler')
            ->willReturn('http://compiler/url');
        $apiHandler->expects($this->once())->method('postRawData')
            ->with('http://compiler/url', '{"files":[],"libraries":[]}')
            ->willReturn('{"success":false,"message":"someError"}');

        $functionResponse = $function->invoke($controller, ['files' => []]);

        $this->assertEquals(
            '{"success":false,"message":"someError","step":"unknown","additionalCode":[]}',
            $functionResponse
        );
    }

    public function testCompileFalseCompilationWithStepIncluded() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'getRequest', 'addUserIdProjectIdIfNotInRequest', 'returnProvidedAndFetchedLibraries'])
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('compile');

        $controller->expects($this->at(0))->method('get')->with('codebender_builder.handler')
            ->willReturn($apiHandler);
        $controller->expects($this->at(1))->method('addUserIdProjectIdIfNotInRequest')
            ->with(['files' => [], 'libraries' => []])
            ->willReturn(['files' => [], 'libraries' => []]);
        $controller->expects($this->at(2))->method('returnProvidedAndFetchedLibraries')->with([], [])
            ->willReturn(['libraries' => []]);

        $container->expects($this->once())->method('getParameter')->with('compiler')
            ->willReturn('http://compiler/url');
        $apiHandler->expects($this->once())->method('postRawData')
            ->with('http://compiler/url', '{"files":[],"libraries":[]}')
            ->willReturn('{"success":false,"message":"someError","step":5}');

        $functionResponse = $function->invoke($controller, ['files' => [], 'libraries' => []]);

        $this->assertEquals('{"success":false,"message":"someError","step":5,"additionalCode":[]}', $functionResponse);
    }

    /*
     * Not much to test here
     */
    public function testGetLibraryInfo() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'getRequest'])
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('getLibraryInfo');

        $controller->expects($this->once())->method('get')->with('codebender_builder.handler')
            ->willReturn($apiHandler);

        $container->expects($this->once())->method('getParameter')->with('library_manager')
            ->will($this->returnValue('http://library/manager'));

        $apiHandler->expects($this->once())->method('postRawData')
            ->with('http://library/manager', 'library data')
            ->willReturn('Whatever');

        $functionResponse = $function->invoke($controller, 'library data');

        $this->assertEquals('Whatever', $functionResponse);
    }

    public function testReturnProvidedAndFetchedLibrariesNoProvidedLibrariesNoProjectLibraries() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'getRequest'])
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('returnProvidedAndFetchedLibraries');

        $controller->expects($this->once())->method('get')->with('codebender_builder.handler')
            ->willReturn($apiHandler);

        $apiHandler->expects($this->once())->method('readLibraries')->with([])
            ->willReturn(array());

        $functionResponse = $function->invokeArgs($controller, [[], []]);

        $this->assertArrayHasKey('libraries', $functionResponse);
        $this->assertEmpty($functionResponse['libraries']);
        $this->assertArrayHasKey('providedLibraries', $functionResponse);
        $this->assertEmpty($functionResponse['providedLibraries']);
        $this->assertArrayHasKey('fetchedLibraries', $functionResponse);
        $this->assertEmpty($functionResponse['fetchedLibraries']);
        $this->assertArrayHasKey('detectedHeaders', $functionResponse);
        $this->assertEmpty($functionResponse['detectedHeaders']);
        $this->assertArrayHasKey('foundHeaders', $functionResponse);
        $this->assertEmpty($functionResponse['foundHeaders']);
        $this->assertArrayHasKey('notFoundHeaders', $functionResponse);
        $this->assertEmpty($functionResponse['notFoundHeaders']);
    }

    public function testReturnProvidedAndFetchedLibrariesNoProvidedLibrariesNeedToFetchALibraryFromLibman() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'getRequest', 'getLibraryInfo'])
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('returnProvidedAndFetchedLibraries');

        $controller->expects($this->once())->method('get')->with('codebender_builder.handler')
            ->willReturn($apiHandler);

        $apiHandler->expects($this->once())->method('readLibraries')->with([])
            ->willReturn(['header']);

        $controller->expects($this->once())->method('getLibraryInfo')
            ->with('{"type":"fetch","library":"header"}')
            ->willReturn(json_encode(['success' => true, 'files' => [['filename' => 'header.h', 'content' => '']]]));

        $functionResponse = $function->invokeArgs($controller, [[], []]);
        $this->assertEquals(['header' => [['filename' => 'header.h', 'content' => '']]], $functionResponse['libraries']);
        $this->assertEquals(['header'], $functionResponse['fetchedLibraries']);
        $this->assertEquals(['header.h'], $functionResponse['foundHeaders']);
    }

    public function testReturnProvidedAndFetchedLibrariesNoProvidedRequestedFromLibmanNotFound() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'getRequest', 'getLibraryInfo'])
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('returnProvidedAndFetchedLibraries');

        $controller->expects($this->once())->method('get')->with('codebender_builder.handler')
            ->willReturn($apiHandler);

        $apiHandler->expects($this->once())->method('readLibraries')->with([])
            ->willReturn(['header']);

        $controller->expects($this->once())->method('getLibraryInfo')
            ->with('{"type":"fetch","library":"header"}')
            ->willReturn(json_encode(['success' => false]));

        $functionResponse = $function->invokeArgs($controller, [[], []]);
        $this->assertEquals([], $functionResponse['libraries']);
        $this->assertEquals([], $functionResponse['fetchedLibraries']);
        $this->assertEquals(['header.h'], $functionResponse['notFoundHeaders']);
    }

    public function testReturnProvidedAndFetchedLibrariesWithProvidedLibrary() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'getRequest', 'getLibraryInfo'])
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('returnProvidedAndFetchedLibraries');

        $controller->expects($this->once())->method('get')->with('codebender_builder.handler')
            ->willReturn($apiHandler);

        $apiHandler->expects($this->once())->method('readLibraries')->with([])->willReturn(['header']);

        $functionResponse = $function->invokeArgs(
            $controller,
            [
                [],
                ['personal_library' => [['filename' => 'header.h', 'content' => '']]]
            ]
        );

        $controller->expects($this->never())->method('getLibraryInfo');

        $this->assertEquals(['personal_library' => [['filename' => 'header.h', 'content' => '']]],
            $functionResponse['libraries']);
        $this->assertEquals(['personal_library'], $functionResponse['providedLibraries']);
        $this->assertEquals(['header.h'], $functionResponse['foundHeaders']);
    }

    public function testcheckUserIdProjectIdHasNone() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('addUserIdProjectIdIfNotInRequest');

        $requestContent = ['files' => [['filename' => 'project.ino', 'content' =>'']]];

        $functionResponse = $function->invoke($controller, $requestContent);

        $this->assertEquals('null', $functionResponse['projectId']);
        $this->assertEquals('null', $functionResponse['userId']);
    }

    public function testcheckUserIdProjectIdHasOnlyUserId() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('addUserIdProjectIdIfNotInRequest');

        $requestContent = ['userId' => 1, 'files' => [['filename' => 'project.ino', 'content' =>'']]];

        $functionResponse = $function->invoke($controller, $requestContent);

        $this->assertEquals('null', $functionResponse['projectId']);
        $this->assertEquals(1, $functionResponse['userId']);
    }

    public function testcheckUserIdProjectIdHasOnlyProjectId() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('addUserIdProjectIdIfNotInRequest');

        $projectFiles = ['projectId' => 1, 'files' => [['filename' => 'project.ino', 'content' =>'']]];

        $functionResponse = $function->invoke($controller, $projectFiles);

        $this->assertEquals(1, $functionResponse['projectId']);
        $this->assertEquals('null', $functionResponse['userId']);
    }

    protected static function getMethod($name) {
        $method = new ReflectionMethod('Codebender\BuilderBundle\Controller\DefaultController', $name);
        $method->setAccessible(true);
        return $method;
	}

    private function setUpController(&$controller, &$container, &$request, &$apiHandler) {
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'getRequest'])
            ->getMock();

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->setMethods(['getParameter'])
            ->getMockForAbstractClass();

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->setMethods(['getContent'])
            ->getMock();

        $apiHandler = $this->getMockBuilder('Codebender\BuilderBundle\Handler\DefaultHandler')
            ->disableOriginalConstructor()
            ->setMethods(['postRawData', 'readLibraries'])
            ->getMock();

        $controller->setContainer($container);
    }
}
