<?php

declare(strict_types=1);

namespace Digitalize\Sms\Exceptions;

use Digitalize\Sms\Http\Response;

/**
 * The API answered with an error. getErrorCode() is the stable code to branch on (see
 * https://digitalize.sd/developers/sms#errors); getMessage() is human-readable Arabic text.
 */
class ApiException extends SmsException
{
    /**
     * @param array<string, mixed>|null $details
     * @param array<string, mixed>|null $data
     * @param array<string, string> $headers
     */
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $httpStatus,
        private readonly ?array $details = null,
        private readonly ?array $data = null,
        private readonly array $headers = [],
    ) {
        parent::__construct($message, $httpStatus);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $error = is_array($body['error'] ?? null) ? $body['error'] : null;

        $code = is_string($error['code'] ?? null) ? $error['code'] : 'invalid_response';
        $message = is_string($error['message'] ?? null)
            ? $error['message']
            : "Unexpected response from the Digitalize SMS API (HTTP {$response->status}).";
        $details = is_array($error['details'] ?? null) ? $error['details'] : null;
        $data = is_array($body['data'] ?? null) ? $body['data'] : null;

        $class = match (true) {
            $response->status === 401 => AuthenticationException::class,
            $response->status === 402 => InsufficientPointsException::class,
            $response->status === 403 => AccountSuspendedException::class,
            $response->status === 404 => NotFoundException::class,
            $response->status === 409 => ConflictException::class,
            $response->status === 400, $response->status === 422 => ValidationException::class,
            $response->status === 429 => RateLimitException::class,
            $code === 'provider_error' => ProviderException::class,
            $response->status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($message, $code, $response->status, $details, $data, $response->headers);
    }

    /** Stable machine-readable code, e.g. "insufficient_points" or "invalid_phone". */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /** @return array<string, mixed>|null extra information, e.g. per-field errors for validation_failed */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /** @return array<string, mixed>|null the "data" the API sent along with the error, if any */
    public function getResponseData(): ?array
    {
        return $this->data;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
