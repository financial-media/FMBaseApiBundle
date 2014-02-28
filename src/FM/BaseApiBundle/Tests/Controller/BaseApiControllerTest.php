<?php

namespace FM\BaseApiBundle\Tests\Controller;

use FM\BaseApiBundle\Controller\BaseApiController;
use FM\BaseApiBundle\Security\SecurityContext;
use FM\BaseApiBundle\Tests\Mock\ApiControllerMock;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ValidatorInterface;

class BaseApiControllerTest extends \PHPUnit_Framework_TestCase
{
    public function testGetNoApiUser()
    {
        $container = $this->getContainerMock(['fm_api.security.context' => $this->getSecurityContextMock()]);

        $controller = new ApiControllerMock();
        $controller->setContainer($container);

        $this->assertNull($controller->getApiUser());
    }

    public function testGetApiUser()
    {
        $securityContext = $this->getSecurityContextMock();
        $container = $this->getContainerMock(['fm_api.security.context' => $securityContext]);

        $controller = new ApiControllerMock();
        $controller->setContainer($container);

        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMockForAbstractClass();

        /** @var TokenInterface|\PHPUnit_Framework_MockObject_MockObject $token */
        $token = $this
            ->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')
            ->setMethods(['getUser'])
            ->getMockForAbstractClass()
        ;
        $token
            ->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue($user))
        ;

        $securityContext
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token))
        ;

        $this->assertSame($user, $controller->getApiUser());
    }

    public function testGetRequestData()
    {
        $container = $this->getContainerMock();
        $controller = new ApiControllerMock();
        $controller->setContainer($container);

        $query = ['foo' => 'bar'];
        $request = Request::create('/foo', 'GET', $query);
        $data = $controller->getRequestData($request);
        $this->assertEquals($query, $data->all(), '->getRequestData for GET request');

        $request = Request::create('/foo', 'POST', [], [], [], [], 'foo');
        $data = $controller->getRequestData($request);
        $this->assertEquals('foo', $data, '->getRequestData for POST request');

        $request = Request::create('/foo', 'PUT', [], [], [], [], 'foo');
        $data = $controller->getRequestData($request);
        $this->assertEquals('foo', $data, '->getRequestData for POST request');

        $request = Request::create('/foo', 'DELETE', [], [], [], [], 'foo');
        $data = $controller->getRequestData($request);
        $this->assertEquals('foo', $data, '->getRequestData for POST request');

        $query = ['foo' => 'bar'];
        $request = Request::create('/foo', 'UNDEF', $query);
        $data = $controller->getRequestData($request);
        $this->assertEquals($query, $data->all(), '->getRequestData for undefined request');
    }

    public function testGetSerializedRequestData()
    {
        $serializer = $this->getSerializerMock();
        $container = $this->getContainerMock(['jms_serializer' => $serializer]);
        $controller = new ApiControllerMock();
        $controller->setContainer($container);

        $serializer
            ->expects($this->once())
            ->method('deserialize')
            ->will($this->returnValue('deserialized!'))
        ;

        $request = Request::create('/foo', 'GET', ['foo' => 'bar']);
        $data = $controller->getRequestData($request, 'test');
        $this->assertEquals('deserialized!', $data, '->getRequestData for GET request, serialized');
    }

    public function testValidate()
    {
        $request = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $validator = $this->getValidatorMock();
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with($request)
            ->will($this->returnValue(new ConstraintViolationList([])))
        ;

        $container = $this->getContainerMock(['validator' => $validator]);
        $controller = new ApiControllerMock();
        $controller->setContainer($container);
        $controller->validate($request);
    }

    /**
     * @expectedException \FM\BaseApiBundle\Exception\ValidationException
     */
    public function testValidateWithError()
    {
        $request = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $violation = $this
            ->getMockBuilder('Symfony\Component\Validator\ConstraintViolation')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $validator = $this->getValidatorMock();
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with($request)
            ->will($this->returnValue(new ConstraintViolationList([$violation])))
        ;

        $container = $this->getContainerMock(['validator' => $validator]);
        $controller = new ApiControllerMock();
        $controller->setContainer($container);

        $controller->validate($request);
    }

    public function testCreateResponse()
    {
        $container = $this->getContainerMock();
        $controller = new ApiControllerMock();
        $controller->setContainer($container);

        // create default response
        $response = $controller->createResponse();
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // create response with different code
        $response = $controller->createResponse(Response::HTTP_I_AM_A_TEAPOT);
        $this->assertEquals(Response::HTTP_I_AM_A_TEAPOT, $response->getStatusCode());
    }

    public function testRenderResponse()
    {
        $templating = $this->getTemplatingMock();
        $container = $this->getContainerMock(['templating' => $templating]);
        $controller = new ApiControllerMock();
        $controller->setContainer($container);

        $templating
            ->expects($this->once())
            ->method('renderResponse')
            ->will($this->returnArgument(1))
        ;

        $response = $controller->renderResponse(['foo' => 'bar'], true, Response::HTTP_OK);
        $data = $response['data'];

        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('ok', $data);
        $this->assertTrue($data['ok']);
        $this->assertArrayHasKey('foo', $data);
        $this->assertEquals('bar', $data['foo']);
    }

    public function testRenderOk()
    {
        /** @var BaseApiController|\PHPUnit_Framework_MockObject_MockObject $controller */
        $controller = $this
            ->getMockBuilder('FM\BaseApiBundle\Controller\BaseApiController')
            ->setMethods(['renderResponse'])
            ->getMockForAbstractClass()
        ;

        $data = ['foo' => 'bar'];
        $ok   = true;
        $code = Response::HTTP_OK;
        $meta = ['metafoo' => 'metabar'];

        $result = [
            'metadata' => $meta,
            'result' => $data
        ];

        $controller
            ->expects($this->once())
            ->method('renderResponse')
            ->with($result, $ok, $code)
        ;

        $controller->renderOk($data, $code, [], $meta);
    }

    public function testRenderError()
    {
        /** @var BaseApiController|\PHPUnit_Framework_MockObject_MockObject $controller */
        $controller = $this
            ->getMockBuilder('FM\BaseApiBundle\Controller\BaseApiController')
            ->setMethods(['renderResponse'])
            ->getMockForAbstractClass()
        ;

        $ok    = false;
        $code  = Response::HTTP_FORBIDDEN;
        $error = 'oh noes!';

        $result = [
            'error' => $error
        ];

        $controller
            ->expects($this->once())
            ->method('renderResponse')
            ->with($result, $ok, $code)
        ;

        $controller->renderError($code, $error);
    }

    /**
     * @param array $gets
     *
     * @return ContainerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getContainerMock(array $gets = [])
    {
        $container = $this
            ->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass()
        ;

        $num = 0;
        foreach ($gets as $id => $service) {
            $num++;
            $container
                ->expects($this->exactly($num))
                ->method('get')
                ->with($id)
                ->will($this->returnValue($service))
            ;
        }

        return $container;
    }

    /**
     * @return SecurityContext|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getSecurityContextMock()
    {
        return $this
            ->getMockBuilder('FM\BaseApiBundle\Security\SecurityContext')
            ->getMock()
        ;
    }

    /**
     * @return SerializerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getSerializerMock()
    {
        return $this
            ->getMockBuilder('JMS\Serializer\SerializerInterface')
            ->getMock()
        ;
    }

    /**
     * @return EngineInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getTemplatingMock()
    {
        return $this
            ->getMockBuilder('Symfony\Bundle\FrameworkBundle\Templating\EngineInterface')
            ->getMock()
        ;
    }

    /**
     * @return ValidatorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getValidatorMock()
    {
        return $this
            ->getMockBuilder('Symfony\Component\Validator\ValidatorInterface')
            ->getMock()
        ;
    }
}
