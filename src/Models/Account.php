<?php

declare(strict_types=1);

namespace Digitalize\Sms\Models;

final class Account
{
    public function __construct(
        public readonly float $pointsBalance,
        public readonly float $pointsPerMessage,
        /** Single-part messages the balance pays for at the current rate. */
        public readonly int $messagesAvailable,
        public readonly bool $smsSuspended,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            pointsBalance: (float) ($data['points_balance'] ?? 0),
            pointsPerMessage: (float) ($data['points_per_message'] ?? 0),
            messagesAvailable: (int) ($data['messages_available'] ?? 0),
            smsSuspended: (bool) ($data['sms_suspended'] ?? false),
        );
    }
}
