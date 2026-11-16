<?php

declare(strict_types=1);

namespace Digitalize\Sms\Exceptions;

/** SMS sending is suspended for this account (403). */
class AccountSuspendedException extends ApiException
{
}
