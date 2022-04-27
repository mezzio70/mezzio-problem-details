<?php

declare(strict_types=1);

namespace MezzioTest\ProblemDetails;

use Mezzio\ProblemDetails\ProblemDetailsNotFoundHandler;
use Mezzio\ProblemDetails\ProblemDetailsNotFoundHandlerFactory;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionObject;
use RuntimeException;

class ProblemDetailsNotFoundHandlerFactoryTest extends TestCase
{
    /** @var ContainerInterface&MockObject */
    private $container;

    /** @var ProblemDetailsNotFoundHandlerFactory */
    private $factory;

    /**
     * @return void
     */
    protected function setUp()
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory   = new ProblemDetailsNotFoundHandlerFactory();
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
    public function testCreatesNotFoundHandlerUsingResponseFactoryService()
    {
        $responseFactory = $this->createMock(ProblemDetailsResponseFactory::class);
        $this->container
            ->method('get')
            ->with(ProblemDetailsResponseFactory::class)
            ->willReturn($responseFactory);

        $notFoundHandler = ($this->factory)($this->container);

        $r = (new ReflectionObject($notFoundHandler))->getProperty('responseFactory');
        $r->setAccessible(true);

        $this->assertInstanceOf(ProblemDetailsNotFoundHandler::class, $notFoundHandler);
        $this->assertSame($responseFactory, $r->getValue($notFoundHandler));
    }
}
