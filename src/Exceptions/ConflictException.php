<?php

declare(strict_types=1);

namespace Digitalize\Sms\Exceptions;

/** The idempotency key was used for a different request, or its first request is still running (409). */
class ConflictException extends ApiException
{
}
