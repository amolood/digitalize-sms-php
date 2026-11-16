<?php

declare(strict_types=1);

namespace Digitalize\Sms\Tests;

use Digitalize\Sms\Client;
use Digitalize\Sms\Laravel\DigitalizeSmsChannel;
use Digitalize\Sms\Laravel\DigitalizeSmsMessage;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\TestCase;

final class LaravelChannelTest extends TestCase
{
    private FakeTransport $http;
    private DigitalizeSmsChannel $channel;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $client = new Client('sms_test', ['transport' => $this->http, 'sleep' => fn () => null]);
        $this->channel = new DigitalizeSmsChannel($client);
    }

    private function user(?string $phone): object
    {
        return new class($phone) {
            public function __construct(public ?string $phone) {}

            public function routeNotificationFor(string $driver, $notification = null): ?string
            {
                return $driver === 'digitalize-sms' ? $this->phone : null;
            }
        };
    }

    private function otpNotification(string $id = 'b9a2c3d4-0000-4000-8000-000000000001'): Notification
    {
        $notification = new class extends Notification {
            public function toDigitalizeSms($notifiable): DigitalizeSmsMessage
            {
                return DigitalizeSmsMessage::otp(12, '482913');
            }
        };
        $notification->id = $id;

        return $notification;
    }

    public function test_sends_to_the_routed_phone_with_a_key_derived_from_the_notification(): void
    {
        $this->http->push(201, ['success' => true, 'data' => ['id' => 9, 'status' => 'submitted']]);

        $message = $this->channel->send($this->user('0912345678'), $this->otpNotification());

        $request = $this->http->last();
        $this->assertSame(['template_id' => 12, 'phone' => '0912345678', 'variables' => ['code' => '482913']], json_decode($request['body'], true));
        $this->assertStringStartsWith('ntf_b9a2c3d4-0000-4000-8000-000000000001_', $request['headers']['Idempotency-Key']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_.:-]{8,100}$/', $request['headers']['Idempotency-Key']);
        $this->assertSame(9, $message?->id);
    }

    public function test_a_retried_queued_notification_reuses_its_key(): void
    {
        $this->http->push(201, ['success' => true, 'data' => ['id' => 9]])->push(200, ['success' => true, 'data' => ['id' => 9]]);

        $this->channel->send($this->user('0912345678'), $this->otpNotification());
        $this->channel->send($this->user('0912345678'), $this->otpNotification());

        $this->assertSame($this->http->requests[0]['headers']['Idempotency-Key'], $this->http->requests[1]['headers']['Idempotency-Key']);
    }

    public function test_nothing_is_sent_without_a_phone(): void
    {
        $this->assertNull($this->channel->send($this->user(null), $this->otpNotification()));
        $this->assertSame([], $this->http->requests);
    }

    public function test_the_message_can_name_its_own_recipient_and_variables(): void
    {
        $this->http->push(201, ['success' => true, 'data' => ['id' => 10]]);
        $notification = new class extends Notification {
            public function toDigitalizeSms($notifiable): DigitalizeSmsMessage
            {
                return DigitalizeSmsMessage::template(15)->variables(['order' => 'A-17'])->with('total', 1500)->to('+249912345678')->idempotencyKey('order-A-17-ready');
            }
        };

        $this->channel->send($this->user(null), $notification);

        $request = $this->http->last();
        $this->assertSame(['template_id' => 15, 'phone' => '+249912345678', 'variables' => ['order' => 'A-17', 'total' => 1500]], json_decode($request['body'], true));
        $this->assertSame('order-A-17-ready', $request['headers']['Idempotency-Key']);
    }

    public function test_a_notification_without_to_digitalize_sms_fails_loudly(): void
    {
        $this->expectException(\LogicException::class);
        $this->channel->send($this->user('0912345678'), new class extends Notification {});
    }
}
