<?php

declare(strict_types=1);

namespace Digitalize\Sms\Exceptions;

/** Too many requests (429). getRetryAfter() is how many seconds to wait. */
class RateLimitException extends ApiException
{
    public function getRetryAfter(): ?int
    {
        $value = $this->getHeader('Retry-After');

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }
}
