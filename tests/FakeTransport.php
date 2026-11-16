<?php

declare(strict_types=1);

namespace Digitalize\Sms\Tests;

use Digitalize\Sms\Exceptions\TransportException;
use Digitalize\Sms\Http\Response;
use Digitalize\Sms\Http\Transport;

/** Answers with queued responses (or throws queued exceptions) and records every request. */
final class FakeTransport implements Transport
{
    /** @var list<Response|TransportException> */
    private array $queue = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];

    public function push(int $status, array $body, array $headers = []): self
    {
        $this->queue[] = new Response($status, array_change_key_case($headers), json_encode($body, JSON_UNESCAPED_UNICODE));

        return $this;
    }

    public function pushRaw(int $status, string $body): self
    {
        $this->queue[] = new Response($status, [], $body);

        return $this;
    }

    public function fail(string $message = 'Operation timed out'): self
    {
        $this->queue[] = new TransportException($message, 28);

        return $this;
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout, float $connectTimeout): Response
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');

        $next = array_shift($this->queue) ?? throw new \LogicException("No fake response queued for {$method} {$url}");
        if ($next instanceof TransportException) {
            throw $next;
        }

        return $next;
    }

    public function last(): array
    {
        return $this->requests[array_key_last($this->requests)];
    }
}
