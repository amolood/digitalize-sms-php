<?php

declare(strict_types=1);

namespace Digitalize\Sms\Laravel;

/**
 * What a notification's toDigitalizeSms() returns:
 *
 *     return DigitalizeSmsMessage::otp(12, $this->code);
 *     return DigitalizeSmsMessage::template(15)->with('order', $order->number);
 */
final class DigitalizeSmsMessage
{
    /** @var array<string, string|int|float> */
    public array $variables = [];
    public ?string $phone = null;
    public ?string $idempotencyKey = null;

    public function __construct(public int $templateId)
    {
    }

    public static function template(int $templateId): self
    {
        return new self($templateId);
    }

    public static function otp(int $templateId, string $code): self
    {
        return (new self($templateId))->with('code', $code);
    }

    public function with(string $variable, string|int|float $value): self
    {
        $this->variables[$variable] = $value;

        return $this;
    }

    /** @param array<string, string|int|float> $variables */
    public function variables(array $variables): self
    {
        $this->variables = $variables + $this->variables;

        return $this;
    }

    /** Send to this number instead of the notifiable's routeNotificationForDigitalizeSms(). */
    public function to(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    /** A unique id for this message, so a retried queued notification is sent once. */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }
}
