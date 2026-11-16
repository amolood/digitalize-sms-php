<?php

declare(strict_types=1);

namespace Digitalize\Sms\Laravel;

use Digitalize\Sms\Client;
use Illuminate\Support\Facades\Facade;

/**
 * DigitalizeSms::messages()->send(12, '0912345678', ['code' => '482913']);
 *
 * @method static \Digitalize\Sms\Resources\MessagesResource messages()
 * @method static \Digitalize\Sms\Resources\TemplatesResource templates()
 * @method static \Digitalize\Sms\Resources\AccountResource account()
 *
 * @see Client
 */
final class DigitalizeSms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
