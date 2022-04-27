<?php

declare(strict_types=1);

namespace MezzioTest\ProblemDetails\Response;

use Mezzio\ProblemDetails\Response\CallableResponseFactoryDecorator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class CallableResponseFactoryDecoratorTest extends TestCase
{
    /** @var MockObject&ResponseInterface */
    private $response;

    /** @var CallableResponseFactoryDecorator */
    private $factory;

    /**
     * @return void
     */
    protected function setUp()
    {
        parent::setUp();
        $this->response = $this->createMock(ResponseInterface::class);
        $this->factory  = new CallableResponseFactoryDecorator(function (): ResponseInterface {
            return $this->response;
        });
    }

    /**
     * @return void
     */
    public function testWillPassStatusCodeAndPhraseToCallable()
    {
        $this->response
            ->expects(self::once())
            ->method('withStatus')
            ->with(500, 'Foo')
            ->willReturnSelf();

        $this->factory->createResponse(500, 'Foo');
    }

    /**
     * @return void
     */
    public function testWillReturnSameResponseInstance()
    {
        $this->response
            ->expects(self::once())
            ->method('withStatus')
            ->willReturnSelf();

        self::assertEquals($this->response, $this->factory->createResponse());
    }
}
