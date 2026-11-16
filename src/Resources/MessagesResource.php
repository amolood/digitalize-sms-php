<?php

declare(strict_types=1);

namespace Digitalize\Sms\Resources;

use Digitalize\Sms\Client;
use Digitalize\Sms\Exceptions\ApiException;
use Digitalize\Sms\Exceptions\TransportException;
use Digitalize\Sms\Models\Message;
use Digitalize\Sms\Models\Page;

final class MessagesResource
{
    /** @internal */
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Sends one message from an approved template.
     *
     * @param int $templateId the template's id (client area or templates()->list())
     * @param string $phone a Sudanese mobile number in any format: 0912345678, +249 912 345 678, 00249…
     * @param array<string, string|int|float> $variables one value per template variable, e.g. ['code' => '482913']
     * @param string|null $idempotencyKey a unique id for this message (your order id, a UUID…). Give one when
     *        your own code may repeat the call, e.g. a queued job that retries; the message then goes out once.
     *        Without one a random key is generated, which still makes this client's own retries safe.
     * @throws ApiException InsufficientPointsException, ValidationException, ProviderException, RateLimitException…
     * @throws TransportException
     */
    public function send(int $templateId, string $phone, array $variables = [], ?string $idempotencyKey = null): Message
    {
        $idempotencyKey ??= 'sdk_' . bin2hex(random_bytes(16));

        $payload = ['template_id' => $templateId, 'phone' => $phone];
        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        $response = $this->client->request('POST', 'messages', json: $payload, headers: ['Idempotency-Key' => $idempotencyKey]);

        return Message::fromArray(
            $response->json()['data'] ?? [],
            replayed: strtolower((string) $response->header('Idempotent-Replayed')) === 'true',
        );
    }

    /** Shortcut for an OTP template, whose only variable is {code}. */
    public function sendOtp(int $templateId, string $phone, string $code, ?string $idempotencyKey = null): Message
    {
        return $this->send($templateId, $phone, ['code' => $code], $idempotencyKey);
    }

    public function get(int $id): Message
    {
        return Message::fromArray($this->client->request('GET', "messages/{$id}")->json()['data'] ?? []);
    }

    /**
     * One page of sent messages, newest first.
     *
     * @param array{status?: string, template_id?: int, phone?: string, from?: string|\DateTimeInterface,
     *              to?: string|\DateTimeInterface, per_page?: int, page?: int} $filters
     * @return Page<Message>
     */
    public function list(array $filters = []): Page
    {
        foreach (['from', 'to'] as $date) {
            if (($filters[$date] ?? null) instanceof \DateTimeInterface) {
                $filters[$date] = $filters[$date]->format(\DateTimeInterface::ATOM);
            }
        }

        $body = $this->client->request('GET', 'messages', $filters)->json() ?? [];
        $meta = $body['meta'] ?? [];

        return new Page(
            items: array_map(static fn (array $item) => Message::fromArray($item), $body['data'] ?? []),
            currentPage: (int) ($meta['current_page'] ?? 1),
            perPage: (int) ($meta['per_page'] ?? 0),
            total: (int) ($meta['total'] ?? 0),
            lastPage: (int) ($meta['last_page'] ?? 1),
        );
    }

    /**
     * Every message matching the filters, fetching pages as you iterate.
     *
     * @param array<string, mixed> $filters same as list(), without page/per_page
     * @return \Generator<int, Message>
     */
    public function all(array $filters = []): \Generator
    {
        $filters['per_page'] = 100;
        $filters['page'] = 1;

        do {
            $page = $this->list($filters);
            yield from $page->items;
            $filters['page']++;
        } while ($page->hasMorePages());
    }
}
