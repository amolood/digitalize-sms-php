<?php

declare(strict_types=1);

namespace Digitalize\Sms;

use Digitalize\Sms\Exceptions\ApiException;
use Digitalize\Sms\Exceptions\TransportException;
use Digitalize\Sms\Http\CurlTransport;
use Digitalize\Sms\Http\Response;
use Digitalize\Sms\Http\Transport;
use Digitalize\Sms\Resources\AccountResource;
use Digitalize\Sms\Resources\MessagesResource;
use Digitalize\Sms\Resources\TemplatesResource;

/**
 * Client for the Digitalize Lab SMS API (https://digitalize.sd/developers/sms).
 *
 *     $sms = new Client(getenv('DIGITALIZE_SMS_KEY'));
 *     $message = $sms->messages()->send(12, '0912345678', ['code' => '482913']);
 *
 * Every send carries an idempotency key (generated when you don't give one), so the client can retry
 * timeouts, 429s and 5xx errors on its own without ever sending a message twice.
 */
final class Client
{
    public const VERSION = '1.0.1';
    public const DEFAULT_BASE_URL = 'https://digitalize.sd/api/v1/sms';

    private readonly string $baseUrl;
    private readonly float $timeout;
    private readonly float $connectTimeout;
    private readonly int $maxRetries;
    private readonly float $maxRetryWait;
    private readonly Transport $transport;
    /** @var callable(float): void */
    private $sleep;

    private ?MessagesResource $messages = null;
    private ?TemplatesResource $templates = null;
    private ?AccountResource $account = null;

    /**
     * @param string $apiKey your key from the client area ("sms_…")
     * @param array{
     *     base_url?: string,
     *     timeout?: float|int,
     *     connect_timeout?: float|int,
     *     max_retries?: int,
     *     max_retry_wait?: float|int,
     *     transport?: Transport,
     *     sleep?: callable(float): void,
     * } $options
     *   - timeout: seconds for a whole request (default 15)
     *   - connect_timeout: seconds to connect (default 5)
     *   - max_retries: extra attempts after a timeout, 429 or 5xx (default 2; 0 disables)
     *   - max_retry_wait: longest Retry-After, in seconds, the client will wait out itself (default 10)
     */
    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        array $options = [],
    ) {
        if (!preg_match('/^sms_[A-Za-z0-9]+$/D', $apiKey)) {
            throw new \InvalidArgumentException('The Digitalize SMS API key must start with "sms_". Create one at https://digitalize.sd/sms/api-keys');
        }

        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        if (!preg_match('#^https?://#i', $this->baseUrl)) {
            throw new \InvalidArgumentException('base_url must be an http(s) URL.');
        }

        $this->timeout = (float) ($options['timeout'] ?? 15);
        $this->connectTimeout = (float) ($options['connect_timeout'] ?? 5);
        $this->maxRetries = max(0, (int) ($options['max_retries'] ?? 2));
        $this->maxRetryWait = (float) ($options['max_retry_wait'] ?? 10);
        $this->transport = $options['transport'] ?? new CurlTransport();
        $this->sleep = $options['sleep'] ?? static function (float $seconds): void {
            usleep((int) ($seconds * 1_000_000));
        };
    }

    public function messages(): MessagesResource
    {
        return $this->messages ??= new MessagesResource($this);
    }

    public function templates(): TemplatesResource
    {
        return $this->templates ??= new TemplatesResource($this);
    }

    public function account(): AccountResource
    {
        return $this->account ??= new AccountResource($this);
    }

    /** Hide the key from var_dump()/print_r() and error trackers. */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'apiKey' => substr($this->apiKey, 0, 8) . '…'];
    }

    /**
     * Sends a request and returns the successful response, retrying transient failures.
     * Only call with requests that are safe to repeat: GETs, and POSTs carrying an Idempotency-Key.
     *
     * @internal used by the resource classes
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     * @throws ApiException
     * @throws TransportException
     */
    public function request(string $method, string $path, array $query = [], ?array $json = null, #[\SensitiveParameter] array $headers = []): Response
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'digitalize-sms-php/' . self::VERSION . ' PHP/' . PHP_VERSION,
        ] + $headers;

        $body = null;
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->transport->send($method, $url, $headers, $body, $this->timeout, $this->connectTimeout);
            } catch (TransportException $e) {
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }
                ($this->sleep)($this->backoff($attempt));
                continue;
            }

            if ($response->status >= 200 && $response->status < 300) {
                return $response;
            }

            $error = ApiException::fromResponse($response);
            $wait = $this->retryWait($error, $response, $attempt);
            if ($wait === null) {
                throw $error;
            }
            ($this->sleep)($wait);
        }
    }

    /** Seconds to wait before retrying, or null when this error must not (or can no longer) be retried. */
    private function retryWait(ApiException $error, Response $response, int $attempt): ?float
    {
        if ($attempt >= $this->maxRetries) {
            return null;
        }

        $transient = in_array($response->status, [429, 500, 503, 504], true)
            || $error->getErrorCode() === 'idempotency_request_in_progress';
        if (!$transient) {
            return null;
        }

        $retryAfter = $response->header('Retry-After');
        $wait = $retryAfter !== null && is_numeric($retryAfter) ? (float) $retryAfter : $this->backoff($attempt);

        return $wait <= $this->maxRetryWait ? $wait : null;
    }

    /** 0.5s, 1s, 2s… with jitter so many clients don't retry in lockstep. */
    private function backoff(int $attempt): float
    {
        return min(0.5 * (2 ** $attempt), 8) * (0.75 + mt_rand() / mt_getrandmax() * 0.5);
    }
}
