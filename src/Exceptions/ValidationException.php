<?php

declare(strict_types=1);

namespace Digitalize\Sms\Exceptions;

/**
 * The request was refused as invalid (400/422): a missing field, an invalid phone number, variables
 * that don't match the template, an inactive template or sender ID. getErrorCode() says which.
 */
class ValidationException extends ApiException
{
    /** @return array<string, list<string>> per-field messages when the code is validation_failed */
    public function getErrors(): array
    {
        return $this->getDetails() ?? [];
    }
}
