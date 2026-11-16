<?php

declare(strict_types=1);

namespace Digitalize\Sms\Resources;

use Digitalize\Sms\Client;
use Digitalize\Sms\Models\Template;

final class TemplatesResource
{
    /** @internal */
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array{type?: 'otp'|'alert', status?: string} $filters
     * @return list<Template>
     */
    public function list(array $filters = []): array
    {
        $data = $this->client->request('GET', 'templates', $filters)->json()['data'] ?? [];

        return array_map(static fn (array $item) => Template::fromArray($item), $data);
    }

    public function get(int $id): Template
    {
        return Template::fromArray($this->client->request('GET', "templates/{$id}")->json()['data'] ?? []);
    }
}
