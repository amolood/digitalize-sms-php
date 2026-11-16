<?php

declare(strict_types=1);

namespace Digitalize\Sms\Models;

/** A message sent through the API. See https://digitalize.sd/developers/sms#message-object */
final class Message
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_UNDELIVERED = 'undelivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNKNOWN = 'unknown';

    public function __construct(
        public readonly int $id,
        public readonly int $templateId,
        public readonly string $phone,
        public readonly string $status,
        public readonly int $parts,
        public readonly float $pointsCharged,
        public readonly ?string $error,
        public readonly ?string $idempotencyKey,
        public readonly ?\DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $sentAt,
        /** True when the API returned an earlier send for the same idempotency key instead of sending again. */
        public readonly bool $replayed = false,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, bool $replayed = false): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            templateId: (int) ($data['template_id'] ?? 0),
            phone: (string) ($data['phone'] ?? ''),
            status: (string) ($data['status'] ?? self::STATUS_UNKNOWN),
            parts: (int) ($data['parts'] ?? 0),
            pointsCharged: (float) ($data['points_charged'] ?? 0),
            error: isset($data['error']) ? (string) $data['error'] : null,
            idempotencyKey: isset($data['idempotency_key']) ? (string) $data['idempotency_key'] : null,
            createdAt: Dates::parse($data['created_at'] ?? null),
            sentAt: Dates::parse($data['sent_at'] ?? null),
            replayed: $replayed,
        );
    }

    /** Accepted by the provider (or delivered). */
    public function isSent(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_DELIVERED], true);
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_UNDELIVERED], true);
    }
}
