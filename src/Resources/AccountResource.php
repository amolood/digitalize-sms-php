<?php

declare(strict_types=1);

namespace Digitalize\Sms\Resources;

use Digitalize\Sms\Client;
use Digitalize\Sms\Models\Account;

final class AccountResource
{
    /** @internal */
    public function __construct(private readonly Client $client)
    {
    }

    /** Points balance and how many messages it pays for. */
    public function get(): Account
    {
        return Account::fromArray($this->client->request('GET', 'account')->json()['data'] ?? []);
    }
}
