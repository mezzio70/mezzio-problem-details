<?php

declare(strict_types=1);

namespace MezzioTest\ProblemDetails;

use Exception;
use Mezzio\ProblemDetails\Exception\CommonProblemDetailsExceptionTrait;
use Mezzio\ProblemDetails\Exception\ProblemDetailsExceptionInterface;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function array_keys;
use function chr;
use function fclose;
use function fopen;
use function json_decode;
use function stripos;

class ProblemDetailsResponseFactoryTest extends TestCase
{
    use ProblemDetailsAssertionsTrait;

    /** @var ServerRequestInterface&MockObject */
    private $request;

    /** @var ResponseInterface&MockObject */
    private $response;

    /** @var ProblemDetailsResponseFactory */
    private $factory;

    const UTF_8_INVALID_2_OCTET_SEQUENCE = "\xc3\x28";

    /**
     * @return void
     */
    protected function setUp()
    {
        $this->request  = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->factory  = new ProblemDetailsResponseFactory(function () {
            return $this->response;
        });
    }

    public function acceptHeaders(): array
    {
        return [
            'empty'                    => ['', 'application/problem+json'],
            'application/xml'          => ['application/xml', 'application/problem+xml'],
            'application/vnd.api+xml'  => ['application/vnd.api+xml', 'application/problem+xml'],
            'application/json'         => ['application/json', 'application/problem+json'],
            'application/vnd.api+json' => ['application/vnd.api+json', 'application/problem+json'],
        ];
    }

    /**
     * @dataProvider acceptHeaders
     * @param string $header
     * @param string $expectedType
     * @return void
     */
    public function testCreateResponseCreatesExpectedType($header, $expectedType)
    {
        $this->request->method('getHeaderLine')->with('Accept')->willReturn($header);

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->atLeastOnce())->method('write')->with($this->isType('string'));

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(500)->willReturn($this->response);
        $this->response->method('withHeader')->with('Content-Type', $expectedType)->willReturn($this->response);

        $response = $this->factory->createResponse(
            $this->request,
            500,
            'Unknown error occurred'
        );

        $this->assertSame($this->response, $response);
    }

    /**
     * @dataProvider acceptHeaders
     * @param string $header
     * @param string $expectedType
     * @return void
     */
    public function testCreateResponseFromThrowableCreatesExpectedType($header, $expectedType)
    {
        $this->request->method('getHeaderLine')->with('Accept')->willReturn($header);

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->atLeastOnce())->method('write')->with($this->isType('string'));

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(500)->willReturn($this->response);
        $this->response->method('withHeader')->with('Content-Type', $expectedType)->willReturn($this->response);

        $exception = new RuntimeException();
        $response  = $this->factory->createResponseFromThrowable(
            $this->request,
            $exception
        );

        $this->assertSame($this->response, $response);
    }

    /**
     * @dataProvider acceptHeaders
     * @param string $header
     * @param string $expectedType
     * @return void
     */
    public function testCreateResponseFromThrowableCreatesExpectedTypeWithExtraInformation(
        $header,
        $expectedType
    ) {
        $this->request->method('getHeaderLine')->with('Accept')->willReturn($header);

        $stream = $this->createMock(StreamInterface::class);
        $this->prepareResponsePayloadAssertions($expectedType, $stream, function (array $payload) {
            Assert::assertArrayHasKey('exception', $payload);
        });

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(500)->willReturn($this->response);
        $this->response->method('withHeader')->with('Content-Type', $expectedType)->willReturn($this->response);

        $factory = new ProblemDetailsResponseFactory(
            function () {
                return $this->response;
            },
            ProblemDetailsResponseFactory::INCLUDE_THROWABLE_DETAILS
        );

        $exception = new RuntimeException();
        $response  = $factory->createResponseFromThrowable(
            $this->request,
            $exception
        );

        $this->assertSame($this->response, $response);
    }

    /**
     * @dataProvider acceptHeaders
     * @param string $header
     * @param string $expectedType
     * @return void
     */
    public function testCreateResponseRemovesInvalidCharactersFromXmlKeys($header, $expectedType)
    {
        $this->request->method('getHeaderLine')->with('Accept')->willReturn($header);

        $additional = [
            'foo' => [
                'A#-'          => 'foo',
                '-A-'          => 'foo',
                '#B-'          => 'foo',
                "C\n-"         => 'foo',
                chr(10) . 'C-' => 'foo',
            ],
        ];

        if (stripos($expectedType, 'xml')) {
            $expectedKeyNames = [
                'A_-',
                '_A-',
                '_B-',
                'C_-',
                '_C-',
            ];
        } else {
            $expectedKeyNames = array_keys($additional['foo']);
        }

        $stream = $this->createMock(StreamInterface::class);
        $this->prepareResponsePayloadAssertions(
            $expectedType,
            $stream,
            function (array $payload) use ($expectedKeyNames) {
                Assert::assertEquals($expectedKeyNames, array_keys($payload['foo']));
            }
        );

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(500)->willReturn($this->response);
        $this->response->method('withHeader')->with('Content-Type', $expectedType)->willReturn($this->response);

        $response = $this->factory->createResponse(
            $this->request,
            500,
            'Unknown error occurred',
            'Title',
            'Type',
            $additional
        );

        $this->assertSame($this->response, $response);
    }

    private function createProblemDetailsExceptionWithAdditional(array $additional): ProblemDetailsExceptionInterface
    {
        return new class (
            400,
            'Exception details',
            'Invalid client request',
            'https://example.com/api/doc/invalid-client-request',
            $additional
        ) extends Exception implements ProblemDetailsExceptionInterface {
            use CommonProblemDetailsExceptionTrait;

            public function __construct(int $status, string $detail, string $title, string $type, array $additional)
            {
                $this->status     = $status;
                $this->detail     = $detail;
                $this->title      = $title;
                $this->type       = $type;
                $this->additional = $additional;
            }
        };
    }

    /**
     * @return void
     */
    public function testCreateResponseFromThrowableWillPullDetailsFromProblemDetailsExceptionInterface()
    {
        $e      = $this->createProblemDetailsExceptionWithAdditional(['foo' => 'bar']);
        $stream = $this->createMock(StreamInterface::class);
        $this->preparePayloadForJsonResponse(
            $stream,
            function (array $payload) {
                Assert::assertSame(400, $payload['status']);
                Assert::assertSame('Exception details', $payload['detail']);
                Assert::assertSame('Invalid client request', $payload['title']);
                Assert::assertSame('https://example.com/api/doc/invalid-client-request', $payload['type']);
                Assert::assertSame('bar', $payload['foo']);
            }
        );

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(400)->willReturn($this->response);
        $this->response
            ->method('withHeader')
            ->with('Content-Type', 'application/problem+json')
            ->willReturn($this->response);

        $factory = new ProblemDetailsResponseFactory(function () {
            return $this->response;
        });

        $response = $factory->createResponseFromThrowable(
            $this->request,
            $e
        );

        $this->assertSame($this->response, $response);
    }

    /**
     * @dataProvider acceptHeaders
     * @param string $header
     * @param string $expectedType
     * @return void
     */
    public function testCreateResponseRemovesResourcesFromInputData($header, $expectedType)
    {
        $this->request->method('getHeaderLine')->with('Accept')->willReturn($header);

        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->expects($this->atLeastOnce())
            ->method('write')
            ->with($this->callback(function ($body) {
                Assert::assertNotEmpty($body);
                return true;
            }));

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(500)->willReturn($this->response);
        $this->response->method('withHeader')->with('Content-Type', $expectedType)->willReturn($this->response);

        $fh       = fopen(__FILE__, 'r');
        $response = $this->factory->createResponse(
            $this->request,
            500,
            'Unknown error occurred',
            'Title',
            'Type',
            [
                'args' => [
                    'resource' => $fh,
                ],
            ]
        );
        fclose($fh);

        $this->assertSame($this->response, $response);
    }

    /**
     * @return void
     */
    public function testFactoryGeneratesXmlResponseIfNegotiationFails()
    {
        $this->request->method('getHeaderLine')->with('Accept')->willReturn('text/plain');

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->atLeastOnce())->method('write')->with($this->isType('string'));

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(500)->willReturn($this->response);
        $this->response
            ->method('withHeader')
            ->with('Content-Type', 'application/problem+xml')
            ->willReturn($this->response);

        $response = $this->factory->createResponse(
            $this->request,
            500,
            'Unknown error occurred'
        );

        $this->assertSame($this->response, $response);
    }

    /**
     * @return void
     */
    public function testFactoryRendersPreviousExceptionsInDebugMode()
    {
        $this->request->method('getHeaderLine')->with('Accept')->willReturn('application/json');

        $stream = $this->createMock(StreamInterface::class);
        $this->preparePayloadForJsonResponse(
            $stream,
            function (array $payload) {
                Assert::assertArrayHasKey('exception', $payload);
                Assert::assertEquals(101011, $payload['exception']['code']);
                Assert::assertEquals('second', $payload['exception']['message']);
                Assert::assertArrayHasKey('stack', $payload['exception']);
                Assert::assertIsArray($payload['exception']['stack']);
                Assert::assertEquals(101010, $payload['exception']['stack'][0]['code']);
                Assert::assertEquals('first', $payload['exception']['stack'][0]['message']);
            }
        );

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(500)->willReturn($this->response);
        $this->response
            ->method('withHeader')
            ->with('Content-Type', 'application/problem+json')
            ->willReturn($this->response);

        $first  = new RuntimeException('first', 101010);
        $second = new RuntimeException('second', 101011, $first);

        $factory = new ProblemDetailsResponseFactory(
            function () {
                return $this->response;
            },
            ProblemDetailsResponseFactory::INCLUDE_THROWABLE_DETAILS
        );

        $response = $factory->createResponseFromThrowable(
            $this->request,
            $second
        );

        $this->assertSame($this->response, $response);
    }

    public function testFragileDataInExceptionMessageShouldBeHiddenInResponseBodyInNoDebugMode()
    {
        $fragileMessage = 'Your SQL or password here';
        $exception      = new Exception($fragileMessage);

        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->expects($this->atLeastOnce())
            ->method('write')
            ->with($this->callback(function (string $body) use ($fragileMessage) {
                Assert::assertStringNotContainsString($fragileMessage, $body);
                Assert::assertStringContainsString(ProblemDetailsResponseFactory::DEFAULT_DETAIL_MESSAGE, $body);
                return true;
            }));

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(500)->willReturn($this->response);
        $this->response
            ->method('withHeader')
            ->with('Content-Type', 'application/problem+json')
            ->willReturn($this->response);

        $response = $this->factory->createResponseFromThrowable($this->request, $exception);

        $this->assertSame($this->response, $response);
    }

    public function testExceptionCodeShouldBeIgnoredAnd500ServedInResponseBodyInNonDebugMode()
    {
        $exception = new Exception('', 400);

        $stream = $this->createMock(StreamInterface::class);
        $this->preparePayloadForJsonResponse(
            $stream,
            function (array $payload) {
                Assert::assertSame(500, $payload['status']);
            }
        );

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(500)->willReturn($this->response);
        $this->response
            ->method('withHeader')
            ->with('Content-Type', 'application/problem+json')
            ->willReturn($this->response);

        $response = $this->factory->createResponseFromThrowable($this->request, $exception);

        $this->assertSame($this->response, $response);
    }

    public function testFragileDataInExceptionMessageShouldBeVisibleInResponseBodyInNonDebugModeWhenAllowToShowByFlag()
    {
        $fragileMessage = 'Your SQL or password here';
        $exception      = new Exception($fragileMessage);

        $stream = $this->createMock(StreamInterface::class);
        $this->preparePayloadForJsonResponse(
            $stream,
            function (array $payload) use ($fragileMessage) {
                Assert::assertSame($fragileMessage, $payload['detail']);
            }
        );

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(500)->willReturn($this->response);
        $this->response
            ->method('withHeader')
            ->with('Content-Type', 'application/problem+json')
            ->willReturn($this->response);

        $factory = new ProblemDetailsResponseFactory(
            function () {
                return $this->response;
            },
            false,
            null,
            true
        );

        $response = $factory->createResponseFromThrowable($this->request, $exception);

        $this->assertSame($this->response, $response);
    }

    public function testCustomDetailMessageShouldBeVisible()
    {
        $detailMessage = 'Custom detail message';

        $stream = $this->createMock(StreamInterface::class);
        $this->preparePayloadForJsonResponse(
            $stream,
            function (array $payload) use ($detailMessage) {
                Assert::assertSame($detailMessage, $payload['detail']);
            }
        );

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(500)->willReturn($this->response);
        $this->response
            ->method('withHeader')
            ->with('Content-Type', 'application/problem+json')
            ->willReturn($this->response);

        $factory = new ProblemDetailsResponseFactory(
            function () {
                return $this->response;
            },
            false,
            null,
            false,
            $detailMessage
        );

        $response = $factory->createResponseFromThrowable($this->request, new Exception());

        $this->assertSame($this->response, $response);
    }

    /**
     * @return void
     */
    public function testRenderWithMalformedUtf8Sequences()
    {
        $e = $this->createProblemDetailsExceptionWithAdditional([
            'malformed-utf8' => self::UTF_8_INVALID_2_OCTET_SEQUENCE,
        ]);

        $this->request->method('getHeaderLine')->with('Accept')->willReturn('application/json');

        $stream = $this->createMock(StreamInterface::class);
        $this->preparePayloadForJsonResponse(
            $stream,
            function (array $payload) {
                Assert::arrayHasKey('malformed-utf8', $payload);
            }
        );

        $this->response->method('getBody')->willReturn($stream);
        $this->response->method('withStatus')->with(400)->willReturn($this->response);
        $this->response
            ->method('withHeader')
            ->with('Content-Type', 'application/problem+json')
            ->willReturn($this->response);

        $factory = new ProblemDetailsResponseFactory(function () {
            return $this->response;
        });

        $response = $factory->createResponseFromThrowable(
            $this->request,
            $e
        );

        $this->assertSame($this->response, $response);
    }

    public function provideMappedStatuses(): array
    {
        $defaultTypesMap = [
            404 => 'https://example.com/problem-details/error/not-found',
            500 => 'https://example.com/problem-details/error/internal-server-error',
        ];

        return [
            [$defaultTypesMap, 404, 'https://example.com/problem-details/error/not-found'],
            [$defaultTypesMap, 500, 'https://example.com/problem-details/error/internal-server-error'],
            [$defaultTypesMap, 400, 'https://httpstatus.es/400'],
            [[], 500, 'https://httpstatus.es/500'],
        ];
    }

    /**
     * @dataProvider provideMappedStatuses
     * @param mixed[] $map
     * @param int $status
     * @param string $expectedType
     * @return void
     */
    public function testTypeIsInferredFromDefaultTypesMap($map, $status, $expectedType)
    {
        $this->request->method('getHeaderLine')->with('Accept')->willReturn('application/json');

        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->expects($this->atLeastOnce())
            ->method('write')
            ->with($this->callback(function (string $body) use ($expectedType) {
                $payload = json_decode($body, true);
                Assert::assertEquals($expectedType, $payload['type']);
                return true;
            }));

        $this->response->method('getBody')->willReturn($stream);
        $this->response
            ->expects($this->atLeastOnce())
            ->method('withStatus')
            ->with($status)
            ->willReturn($this->response);
        $this->response
            ->method('withHeader')
            ->with('Content-Type', 'application/problem+json')
            ->willReturn($this->response);

        $factory = new ProblemDetailsResponseFactory(
            function () {
                return $this->response;
            },
            false,
            null,
            false,
            '',
            $map
        );

        $factory->createResponse($this->request, $status, 'detail');
    }
}
