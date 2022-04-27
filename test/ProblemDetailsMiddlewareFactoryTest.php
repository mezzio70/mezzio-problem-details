<?php

declare(strict_types=1);

namespace MezzioTest\ProblemDetails;

use Mezzio\ProblemDetails\ProblemDetailsMiddleware;
use Mezzio\ProblemDetails\ProblemDetailsMiddlewareFactory;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionObject;
use RuntimeException;

class ProblemDetailsMiddlewareFactoryTest extends TestCase
{
    /** @var ContainerInterface&MockObject */
    private $container;

    /** @var ProblemDetailsMiddlewareFactory */
    private $factory;

    /**
     * @return void
     */
    protected function setUp()
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory   = new ProblemDetailsMiddlewareFactory();
    }

    public function testRaisesExceptionWhenProblemDetailsResponseFactoryServiceIsNotAvailable()
    {
        $e = new RuntimeException();
        $this->container
            ->method('get')
            ->with(ProblemDetailsResponseFactory::class)
            ->willThrowException($e);

        $this->expectException(RuntimeException::class);
        $this->factory->__invoke($this->container);
    }

    /**
     * @return void
     */
    public function testCreatesMiddlewareUsingResponseFactoryService()
    {
        $responseFactory = $this->createMock(ProblemDetailsResponseFactory::class);

        $this->container
            ->method('get')
            ->with(ProblemDetailsResponseFactory::class)
            ->willReturn($responseFactory);

        $middleware = ($this->factory)($this->container);

        $r = (new ReflectionObject($middleware))->getProperty('responseFactory');
        $r->setAccessible(true);

        $this->assertInstanceOf(ProblemDetailsMiddleware::class, $middleware);
        $this->assertSame($responseFactory, $r->getValue($middleware));
    }
}
