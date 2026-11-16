<?php

declare(strict_types=1);

namespace Digitalize\Sms\Laravel;

use Digitalize\Sms\Client;
use Digitalize\Sms\Models\Message;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel. In the notification:
 *
 *     public function via($notifiable) { return [DigitalizeSmsChannel::class]; }   // or 'digitalize-sms'
 *     public function toDigitalizeSms($notifiable) { return DigitalizeSmsMessage::otp(12, $this->code); }
 *
 * and on the notifiable (e.g. User):
 *
 *     public function routeNotificationForDigitalizeSms($notification) { return $this->phone; }
 */
final class DigitalizeSmsChannel
{
    public function __construct(private readonly Client $client)
    {
    }

    public function send(mixed $notifiable, Notification $notification): ?Message
    {
        if (!method_exists($notification, 'toDigitalizeSms')) {
            throw new \LogicException(get_class($notification) . ' must define toDigitalizeSms($notifiable) to use the Digitalize SMS channel.');
        }

        $message = $notification->toDigitalizeSms($notifiable);
        if (!$message instanceof DigitalizeSmsMessage) {
            throw new \LogicException(get_class($notification) . '::toDigitalizeSms() must return a ' . DigitalizeSmsMessage::class . '.');
        }

        $phone = $message->phone;
        if ($phone === null && is_object($notifiable) && method_exists($notifiable, 'routeNotificationFor')) {
            $phone = $notifiable->routeNotificationFor('digitalize-sms', $notification);
        }

        if ($phone === null || $phone === '') {
            return null; // nowhere to send, same as Laravel's own channels
        }

        // A queued notification that's retried keeps its id, so it makes a natural idempotency key
        $key = $message->idempotencyKey
            ?? (isset($notification->id) && is_string($notification->id) && $notification->id !== '' ? 'ntf_' . $notification->id . '_' . substr(sha1((string) $phone), 0, 12) : null);

        return $this->client->messages()->send($message->templateId, (string) $phone, $message->variables, $key);
    }
}
