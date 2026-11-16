<?php

declare(strict_types=1);

namespace Digitalize\Sms\Exceptions;

use Digitalize\Sms\Models\Message;

/**
 * The SMS provider refused the message (502). No points were charged. Retrying with the same
 * idempotency key returns this same failure, so a new attempt needs a new key.
 */
class ProviderException extends ApiException
{
    /** The failed message as recorded by the API (status "failed"). */
    public function getSmsMessage(): ?Message
    {
        $data = $this->getResponseData();

        return $data !== null ? Message::fromArray($data) : null;
    }
}
