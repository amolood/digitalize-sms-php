<?php

declare(strict_types=1);

namespace Digitalize\Sms\Http;

/** A raw HTTP response as returned by a Transport. Header names are lower-case. */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, mixed>|null the decoded JSON object, or null when the body isn't one */
    public function json(): ?array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
