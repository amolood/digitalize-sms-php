<?php

declare(strict_types=1);

namespace Digitalize\Sms\Exceptions;

/** Not enough points for this message (402). Buy a package and retry; the same idempotency key may be reused. */
class InsufficientPointsException extends ApiException
{
}
