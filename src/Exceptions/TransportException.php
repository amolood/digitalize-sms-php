<?php

declare(strict_types=1);

namespace Digitalize\Sms\Exceptions;

/**
 * No response arrived (DNS, connection, TLS, timeout). For a send it is unknown whether the message
 * went out: retry with the same idempotency key and the API will not send it twice.
 */
class TransportException extends SmsException
{
}
