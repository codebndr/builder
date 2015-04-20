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

        $container->expects($this->once())->method('getParameter')->with('auth_key')->will($this->returnValue('anAuthKey'));

        $actionResponse = $controller->handleRequestAction('invalidAuthKey', 'v1');

        $this->assertContains('Invalid authorization key.', $actionResponse->getContent());
    }

    public function testHandleRequestInvalidApiVersion() {

        $this->setUpController($controller, $container, $request, $apiHandler);

        $container->expects($this->at(0))->method('getParameter')->with('auth_key')->will($this->returnValue('anAuthKey'));
        $container->expects($this->at(1))->method('getParameter')->with('version')->will($this->returnValue('v2'));

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('Invalid api version.', $actionResponse->getContent());
    }

    public function testHandleRequestEmptyRequest() {

        $this->setUpController($controller, $container, $request, $apiHandler);

        $container->expects($this->at(0))->method('getParameter')->with('auth_key')->will($this->returnValue('anAuthKey'));
        $container->expects($this->at(1))->method('getParameter')->with('version')->will($this->returnValue('v1'));

        $controller->expects($this->once())->method('getRequest')->will($this->returnValue($request));
        $request->expects($this->once())->method('getContent')->will($this->returnValue(''));

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('Invalid input.', $actionResponse->getContent());
    }

    public function testHandleRequestInvalidJsonDecodesToFalse() {

        $this->setUpController($controller, $container, $request, $apiHandler);

        $container->expects($this->at(0))->method('getParameter')->with('auth_key')->will($this->returnValue('anAuthKey'));
        $container->expects($this->at(1))->method('getParameter')->with('version')->will($this->returnValue('v1'));

        $controller->expects($this->once())->method('getRequest')->will($this->returnValue($request));
        $request->expects($this->once())->method('getContent')->will($this->returnValue("notJson"));

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('Wrong data.', $actionResponse->getContent());
    }

    public function testHandleRequestNoDataKeyInRequest() {

        $this->setUpController($controller, $container, $request, $apiHandler);

        $container->expects($this->at(0))->method('getParameter')->with('auth_key')->will($this->returnValue('anAuthKey'));
        $container->expects($this->at(1))->method('getParameter')->with('version')->will($this->returnValue('v1'));

        $controller->expects($this->once())->method('getRequest')->will($this->returnValue($request));
        $request->expects($this->once())->method('getContent')->will($this->returnValue('{"type":"compiler"}'));

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('Insufficient data provided.', $actionResponse->getContent());
    }

    public function testHandleRequestCompileRequest() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(array('getRequest', 'compile', 'getLibraryInfo'))
            ->getMock();

        $controller->setContainer($container);

        $container->expects($this->at(0))->method('getParameter')->with('auth_key')->will($this->returnValue('anAuthKey'));
        $container->expects($this->at(1))->method('getParameter')->with('version')->will($this->returnValue('v1'));

        $controller->expects($this->once())->method('getRequest')->will($this->returnValue($request));
        $request->expects($this->once())->method('getContent')->will($this->returnValue('{"type":"compiler","data":[]}'));

        $controller->expects($this->once())->method('compile')->with(array())->will($this->returnValue('someValue'));

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('someValue', $actionResponse->getContent());
    }

    public function testHandleRequestLibraryRequest() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(array('getRequest', 'compile', 'getLibraryInfo'))
            ->getMock();

        $controller->setContainer($container);

        $container->expects($this->at(0))->method('getParameter')->with('auth_key')->will($this->returnValue('anAuthKey'));
        $container->expects($this->at(1))->method('getParameter')->with('version')->will($this->returnValue('v1'));

        $controller->expects($this->once())->method('getRequest')->will($this->returnValue($request));
        $request->expects($this->once())->method('getContent')->will($this->returnValue('{"type":"invalidType","data":[]}'));

        $actionResponse = $controller->handleRequestAction('anAuthKey', 'v1');

        $this->assertContains('Invalid request type', $actionResponse->getContent());
    }

    public function testCompileNonJsonCompilerResponse() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(array('get', 'getRequest', 'checkForUserIdProjectId', 'returnProvidedAndFetchedLibraries'))
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('compile');

        $controller->expects($this->at(0))->method('get')->with('codebender_builder.handler')->will($this->returnValue($apiHandler));
        $controller->expects($this->at(1))->method('checkForUserIdProjectId')->with(array());
        $controller->expects($this->at(2))->method('returnProvidedAndFetchedLibraries')->with(array())->will($this->returnValue(array('libraries' => array())));

        $container->expects($this->once())->method('getParameter')->with('compiler')->will($this->returnValue('http://compiler/url'));
        $apiHandler->expects($this->once())->method('postRawData')->with('http://compiler/url', '{"files":[],"libraries":[]}')->will($this->returnValue('nonJson'));

        $functionResponse = $function->invoke($controller, array('files' => array()));

        $this->assertContains('Failed to get compiler response', $functionResponse);
    }

    public function testCompileFalseCompilationWithoutStepIncluded() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(array('get', 'getRequest', 'checkForUserIdProjectId', 'returnProvidedAndFetchedLibraries'))
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('compile');

        $controller->expects($this->at(0))->method('get')->with('codebender_builder.handler')->will($this->returnValue($apiHandler));
        $controller->expects($this->at(1))->method('checkForUserIdProjectId')->with(array());
        $controller->expects($this->at(2))->method('returnProvidedAndFetchedLibraries')->with(array())->will($this->returnValue(array('libraries' => array())));

        $container->expects($this->once())->method('getParameter')->with('compiler')->will($this->returnValue('http://compiler/url'));
        $apiHandler->expects($this->once())->method('postRawData')
            ->with('http://compiler/url', '{"files":[],"libraries":[]}')
            ->will($this->returnValue('{"success":false,"message":"someError"}'));

        $functionResponse = $function->invoke($controller, array('files' => array()));

        $this->assertEquals('{"success":false,"message":"someError","step":"unknown","additionalCode":[]}', $functionResponse);
    }

    public function testCompileFalseCompilationWithStepIncluded() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(array('get', 'getRequest', 'checkForUserIdProjectId', 'returnProvidedAndFetchedLibraries'))
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('compile');

        $controller->expects($this->at(0))->method('get')->with('codebender_builder.handler')->will($this->returnValue($apiHandler));
        $controller->expects($this->at(1))->method('checkForUserIdProjectId')->with(array());
        $controller->expects($this->at(2))->method('returnProvidedAndFetchedLibraries')->with(array())->will($this->returnValue(array('libraries' => array())));

        $container->expects($this->once())->method('getParameter')->with('compiler')->will($this->returnValue('http://compiler/url'));
        $apiHandler->expects($this->once())->method('postRawData')
            ->with('http://compiler/url', '{"files":[],"libraries":[]}')
            ->will($this->returnValue('{"success":false,"message":"someError","step":5}'));

        $functionResponse = $function->invoke($controller, array('files' => array()));

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
            ->setMethods(array('get', 'getRequest'))
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('getLibraryInfo');

        $controller->expects($this->once())->method('get')->with('codebender_builder.handler')->will($this->returnValue($apiHandler));

        $container->expects($this->once())->method('getParameter')->with('library')->will($this->returnValue('http://library/manager'));

        $apiHandler->expects($this->once())->method('postRawData')->with('http://library/manager', 'library data')->will($this->returnValue('Whatever'));

        $functionResponse = $function->invoke($controller, 'library data');

        $this->assertEquals('Whatever', $functionResponse);
    }

    public function testReturnProvidedAndFetchedLibrariesNoProvidedLibrariesNoProjectLibraries() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(array('get', 'getRequest'))
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('returnProvidedAndFetchedLibraries');

        $controller->expects($this->once())->method('get')->with('codebender_builder.handler')->will($this->returnValue($apiHandler));

        $apiHandler->expects($this->once())->method('readLibraries')->with(array())->will($this->returnValue(array()));

        $functionResponse = $function->invokeArgs($controller, array(array(), array()));

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
            ->setMethods(array('get', 'getRequest', 'getLibraryInfo'))
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('returnProvidedAndFetchedLibraries');

        $controller->expects($this->once())->method('get')->with('codebender_builder.handler')->will($this->returnValue($apiHandler));

        $apiHandler->expects($this->once())->method('readLibraries')->with(array())->will($this->returnValue(array('header')));

        $controller->expects($this->once())->method('getLibraryInfo')
            ->with('{"type":"fetch","library":"header"}')
            ->will($this->returnValue(json_encode(array('success' => true, 'files' => array(array('filename' => 'header.h', 'content' => ''))))));

        $functionResponse = $function->invokeArgs($controller, array(array(), array()));
        $this->assertEquals(array('header' => array(array('filename' => 'header.h', 'content' => ''))), $functionResponse['libraries']);
        $this->assertEquals(array('header'), $functionResponse['fetchedLibraries']);
        $this->assertEquals(array('header.h'), $functionResponse['foundHeaders']);
    }

    public function testReturnProvidedAndFetchedLibrariesNoProvidedRequestedFromLibmanNotFound() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(array('get', 'getRequest', 'getLibraryInfo'))
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('returnProvidedAndFetchedLibraries');

        $controller->expects($this->once())->method('get')->with('codebender_builder.handler')->will($this->returnValue($apiHandler));

        $apiHandler->expects($this->once())->method('readLibraries')->with(array())->will($this->returnValue(array('header')));

        $controller->expects($this->once())->method('getLibraryInfo')
            ->with('{"type":"fetch","library":"header"}')
            ->will($this->returnValue(json_encode(array('success' => false))));

        $functionResponse = $function->invokeArgs($controller, array(array(), array()));
        $this->assertEquals(array(), $functionResponse['libraries']);
        $this->assertEquals(array(), $functionResponse['fetchedLibraries']);
        $this->assertEquals(array('header.h'), $functionResponse['notFoundHeaders']);
    }

    public function testReturnProvidedAndFetchedLibrariesWithProvidedLibrary() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        // Override previous controller mock. More class member functions need to get mocked.
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(array('get', 'getRequest', 'getLibraryInfo'))
            ->getMock();

        $controller->setContainer($container);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('returnProvidedAndFetchedLibraries');

        $controller->expects($this->once())->method('get')->with('codebender_builder.handler')->will($this->returnValue($apiHandler));

        $apiHandler->expects($this->once())->method('readLibraries')->with(array())->will($this->returnValue(array('header')));

        $functionResponse = $function->invokeArgs(
            $controller,
            array(
                array(),
                array('personal_library' => array(array('filename' => 'header.h', 'content' => '')))
            )
        );

        $controller->expects($this->never())->method('getLibraryInfo');

        $this->assertEquals(array('personal_library' => array(array('filename' => 'header.h', 'content' => ''))),
            $functionResponse['libraries']);
        $this->assertEquals(array('personal_library'), $functionResponse['providedLibraries']);
        $this->assertEquals(array('header.h'), $functionResponse['foundHeaders']);
    }

    public function testcheckUserIdProjectIdHasNone() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('checkForUserIdProjectId');

        $projectFiles = array(array('filename' => 'project.ino', 'content' =>''));

        /*
         * The function accepts a reference of the parameter
         * TODO: Find a way to replace the Reflection call-time pass-by-reference (removed in PHP 5.4)
         */
        $functionResponse = $function->invoke($controller, &$projectFiles);

        $this->assertEquals(3, count($projectFiles));
        foreach ($projectFiles as $file) {
            $this->assertTrue(in_array($file['filename'], array('project.ino', 'project_null.txt', 'user_null.txt')));
        }
    }

    public function testcheckUserIdProjectIdHasOnlyUserId() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('checkForUserIdProjectId');

        $projectFiles = array(array('filename' => 'project.ino', 'content' =>''), array('filename' => 'user_1.txt', 'content' => ''));

        /*
         * The function accepts a reference of the parameter
         * TODO: Find a way to replace the Reflection call-time pass-by-reference (removed in PHP 5.4)
         */
        $functionResponse = $function->invoke($controller, &$projectFiles);

        $this->assertEquals(3, count($projectFiles));
        foreach ($projectFiles as $file) {
            $this->assertTrue(in_array($file['filename'], array('project.ino', 'project_null.txt', 'user_1.txt')));
        }
    }

    public function testcheckUserIdProjectIdHasOnlyProjectId() {
        $this->setUpController($controller, $container, $request, $apiHandler);

        /*
         * Use ReflectionMethod class to make compile protected function accessible from current context
         */
        $function = $this->getMethod('checkForUserIdProjectId');

        $projectFiles = array(array('filename' => 'project.ino', 'content' =>''), array('filename' => 'project_1.txt', 'content' => ''));

        /*
         * The function accepts a reference of the parameter
         * TODO: Find a way to replace the Reflection call-time pass-by-reference (removed in PHP 5.4)
         */
        $functionResponse = $function->invoke($controller, &$projectFiles);

        $this->assertEquals(3, count($projectFiles));
        foreach ($projectFiles as $file) {
            $this->assertTrue(in_array($file['filename'], array('project.ino', 'project_1.txt', 'user_null.txt')));
        }
    }

    protected static function getMethod($name) {
        $method = new ReflectionMethod('Codebender\BuilderBundle\Controller\DefaultController', $name);
        $method->setAccessible(true);
        return $method;
	}

    private function setUpController(&$controller, &$container, &$request, &$apiHandler) {
        $controller = $this->getMockBuilder('Codebender\BuilderBundle\Controller\DefaultController')
            ->disableOriginalConstructor()
            ->setMethods(array('get', 'getRequest'))
            ->getMock();

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->setMethods(array('getParameter'))
            ->getMockForAbstractClass();

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->setMethods(array('getContent'))
            ->getMock();

        $apiHandler = $this->getMockBuilder('Codebender\BuilderBundle\Handler\DefaultHandler')
            ->disableOriginalConstructor()
            ->setMethods(array('postRawData', 'readLibraries'))
            ->getMock();

        $controller->setContainer($container);
    }
}
