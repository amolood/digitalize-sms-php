<?php

declare(strict_types=1);

namespace Digitalize\Sms\Exceptions;

/** The API key is missing, wrong or revoked (401). */
class AuthenticationException extends ApiException
{
}
