<?php

declare(strict_types=1);

namespace Digitalize\Sms\Exceptions;

/** An error on the API side (5xx). Safe to retry with the same idempotency key. */
class ServerException extends ApiException
{
}
