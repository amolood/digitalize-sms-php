<?php

declare(strict_types=1);

namespace Digitalize\Sms\Models;

final class Template
{
    public const TYPE_OTP = 'otp';
    public const TYPE_ALERT = 'alert';

    /** @param list<string> $variables */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $status,
        /** True when the template is approved and can be sent from. */
        public readonly bool $usable,
        public readonly string $body,
        public readonly array $variables,
        public readonly ?string $sender,
        public readonly ?\DateTimeImmutable $approvedAt,
        public readonly ?\DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            usable: (bool) ($data['usable'] ?? false),
            body: (string) ($data['body'] ?? ''),
            variables: array_values(array_map('strval', (array) ($data['variables'] ?? []))),
            sender: isset($data['sender']) ? (string) $data['sender'] : null,
            approvedAt: Dates::parse($data['approved_at'] ?? null),
            createdAt: Dates::parse($data['created_at'] ?? null),
        );
    }

    public function isOtp(): bool
    {
        return $this->type === self::TYPE_OTP;
    }
}
