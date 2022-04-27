<?php

declare(strict_types=1);

namespace MezzioTest\ProblemDetails;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

use function array_key_exists;

final class InMemoryContainer implements ContainerInterface
{
    /** @var array<string,mixed> */
    private $services = [];

    /**
     * @param string $id
     * @return mixed
     */
    public function get($id)
    {
        if (! $this->has($id)) {
            throw new class ($id . ' was not found') extends RuntimeException implements NotFoundExceptionInterface {
            };
        }

        return $this->services[$id];
    }

    /**
     * @param string $id
     */
    public function has($id): bool
    {
        return array_key_exists($id, $this->services);
    }

    /** @param mixed $item
     * @param string $id
     * @return void */
    public function set($id, $item)
    {
        $this->services[$id] = $item;
    }

    /**
     * @return void
     */
    public function reset()
    {
        $this->services = [];
    }
}
