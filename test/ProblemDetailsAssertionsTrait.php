<?php

declare(strict_types=1);

namespace MezzioTest\ProblemDetails;

use PHPUnit\Framework\Assert;

use function array_walk_recursive;
use function get_class;
use function json_decode;
use function json_encode;
use function simplexml_load_string;
use function sprintf;
use function var_export;

trait ProblemDetailsAssertionsTrait
{
    /**
     * @param mixed[] $expected
     * @param mixed[] $details
     * @return void
     */
    public function assertProblemDetails($expected, $details)
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey(
                $key,
                $details,
                sprintf('Did not find key %s in problem details', $key)
            );

            $this->assertEquals($value, $details[$key], sprintf(
                'Did not find expected value for "%s" key of details; expected "%s", received "%s"',
                $key,
                var_export($value, true),
                var_export($details[$key], true)
            ));
        }
    }

    /**
     * @param Throwable $e
     * @param mixed[] $details
     * @return void
     */
    public function assertExceptionDetails($e, $details)
    {
        $this->assertArrayHasKey('class', $details);
        $this->assertSame(get_class($e), $details['class']);
        $this->assertArrayHasKey('code', $details);
        $this->assertSame($e->getCode(), (int) $details['code']);
        $this->assertArrayHasKey('message', $details);
        $this->assertSame($e->getMessage(), $details['message']);
        $this->assertArrayHasKey('file', $details);
        $this->assertSame($e->getFile(), $details['file']);
        $this->assertArrayHasKey('line', $details);
        $this->assertSame($e->getLine(), (int) $details['line']);

        // PHP does some odd things when creating the trace; individual items
        // may be objects, but once copied, they are arrays. This makes direct
        // comparison impossible; thus, only testing for correct type.
        $this->assertArrayHasKey('trace', $details);
        $this->assertIsArray($details['trace']);
    }

    /**
     * @param MockObject $stream
     * @param string $contentType
     * @param callable $assertion
     * @return void
     */
    public function prepareResponsePayloadAssertions(
        $contentType,
        $stream,
        $assertion
    ) {
        if ('application/problem+json' === $contentType) {
            $this->preparePayloadForJsonResponse($stream, $assertion);
            return;
        }

        if ('application/problem+xml' === $contentType) {
            $this->preparePayloadForXmlResponse($stream, $assertion);
            return;
        }
    }

    /**
     * @param MockObject $stream
     * @param callable $assertion
     * @return void
     */
    public function preparePayloadForJsonResponse($stream, $assertion)
    {
        $stream
            ->expects($this->any())
            ->method('write')
            ->with($this->callback(function ($body) use ($assertion) {
                Assert::assertIsString($body);
                $data = json_decode($body, true);
                $assertion($data);
                return true;
            }));
    }

    /**
     * @param MockObject $stream
     * @param callable $assertion
     * @return void
     */
    public function preparePayloadForXmlResponse($stream, $assertion)
    {
        $stream
            ->expects($this->any())
            ->method('write')
            ->with($this->callback(function ($body) use ($assertion) {
                Assert::assertIsString($body);
                $data = $this->deserializeXmlPayload($body);
                $assertion($data);
                return true;
            }));
    }

    /**
     * @param string $xml
     */
    public function deserializeXmlPayload($xml): array
    {
        $xml     = simplexml_load_string($xml);
        $json    = json_encode($xml);
        $payload = json_decode($json, true);

        // Ensure ints and floats are properly represented
        array_walk_recursive($payload, function (&$item) {
            if ((string) (int) $item === $item) {
                $item = (int) $item;
                return;
            }

            if ((string) (float) $item === $item) {
                $item = (float) $item;
                return;
            }
        });

        return $payload;
    }
}
