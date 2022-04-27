<?php

declare(strict_types=1);

namespace MezzioTest\ProblemDetails;

use Generator;
use Mezzio\Container\ResponseFactoryFactory;
use Mezzio\ProblemDetails\Response\CallableResponseFactoryDecorator;
use MezzioTest\ProblemDetails\TestAsset\Psr17ResponseFactoryTraitImplementation;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final class Psr17ResponseFactoryTraitTest extends TestCase
{
    /** @var Psr17ResponseFactoryTraitImplementation */
    private $factory;

    /**
     * @return void
     */
    protected function setUp()
    {
        parent::setUp();
        $this->factory = new Psr17ResponseFactoryTraitImplementation();
    }

    /**
     * @psalm-return Generator<non-empty-string,array{0:array<string,mixed>}>
     */
    public function configurationsWithOverriddenResponseInterfaceFactory(): Generator
    {
        yield 'default' => [
            [
                'dependencies' => [
                    'factories' => [
                        ResponseInterface::class
                            => function (): ResponseInterface {
                                return $this->createMock(ResponseInterface::class);
                            },
                    ],
                ],
            ],
        ];

        yield 'aliased' => [
            [
                'dependencies' => [
                    'aliases' => [
                        ResponseInterface::class => 'CustomResponseInterface',
                    ],
                ],
            ],
        ];

        yield 'delegated' => [
            [
                'dependencies' => [
                    'delegators' => [
                        ResponseInterface::class => [
                            function (): ResponseInterface {
                                return $this->createMock(ResponseInterface::class);
                            },
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return void
     */
    public function testWillUseResponseFactoryInterfaceFromContainerWhenApplicationFactoryIsNotOverridden()
    {
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $container       = new InMemoryContainer();
        $container->set('config', [
            'dependencies' => [
                'factories' => [
                    ResponseInterface::class => ResponseFactoryFactory::class,
                ],
            ],
        ]);
        $container->set(ResponseFactoryInterface::class, $responseFactory);
        $detectedResponseFactory = ($this->factory)($container);
        self::assertSame($responseFactory, $detectedResponseFactory);
    }

    /**
     * @param array<string,mixed> $config
     * @dataProvider configurationsWithOverriddenResponseInterfaceFactory
     * @return void
     */
    public function testWontUseResponseFactoryInterfaceFromContainerWhenApplicationFactoryIsOverriden(
        $config
    ) {
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $container       = new InMemoryContainer();
        $container->set('config', $config);
        $container->set(ResponseFactoryInterface::class, $responseFactory);
        $response = $this->createMock(ResponseInterface::class);
        $container->set(ResponseInterface::class, static function () use ($response): ResponseInterface {
            return $response;
        });

        $detectedResponseFactory = ($this->factory)($container);
        self::assertNotSame($responseFactory, $detectedResponseFactory);
        self::assertInstanceOf(CallableResponseFactoryDecorator::class, $detectedResponseFactory);
        self::assertEquals($response, $detectedResponseFactory->getResponseFromCallable());
    }
}
