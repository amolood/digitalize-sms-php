<?php

declare(strict_types=1);

namespace Digitalize\Sms\Http;

use Digitalize\Sms\Exceptions\TransportException;

/**
 * Sends one HTTP request. The default is CurlTransport; pass your own through the "transport"
 * option to route requests through a proxy or HTTP client of your choice, or to fake the API in tests.
 */
interface Transport
{
    /**
     * @param array<string, string> $headers
     * @throws TransportException when no HTTP response was received (DNS, connection, TLS, timeout)
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout, float $connectTimeout): Response;
}
